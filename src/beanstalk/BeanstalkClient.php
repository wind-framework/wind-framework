<?php

namespace Wind\Beanstalk;

use Amp\Promise;
use Amp\Success;
use Amp\Deferred;
use function Amp\call;
use function Amp\delay;
use function Amp\asyncCall;

use Wind\Utils\ArrayUtil;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Lib\Timer;

/**
 * 协程 Beanstalk 客户端
 */
class BeanstalkClient
{

	const DEFAULT_PRI = 1024;
	const DEFAULT_TTR = 30;

    /**
     * 是否重连
     * @var bool
     */
    private $autoReconnect;

    /**
     * 重连延迟时间（秒数）
     * @var int
     */
    private $reconnectDelay;

    /**
     * 并否允许并发调用
     * @var bool
     */
    private $concurrent;

    /**
     * 连接超时时间（秒数）
     * @var int
     */
    private $connectTimeout;

	private $connection;
    private $connected = false;

    private $tubeUsed = 'default';
    private $watchTubes = ['default'];

    private $onConnectCallback = null;
    private $onErrorCallback = null;
    private $onCloseCallback = null;

    /**
     * Reading Deferred
     *
     * @var Deferred
     */
    private $pending = null;
    private $connectDefer = null;

    /**
     * 发送中的命令
     * 
     * 用于发送时中断后的命令恢复
     *
     * @var array|null
     */
    private $sending = null;

	public $debug = false;

	/**
	 * BeanstalkClient constructor.
	 *
	 * @param string $host
	 * @param int $port
     * @param array $options 连接选项，数据键值对，支持以下选项：
     * (bool) autoReconnect：是否断线自动重连，默认：否。
     * 自动重连将会自动恢复以往正在发送的命令和动作（主动调用 close 不会）
     * (int) reconnectDelay：重连间隔秒数，默认：5。
     * (bool) concurrent：是否允许并发执行，默认：否。
     * 允许时可以同时调用多个命令，后面的命令会等待前一个命令完成后继续发送。
     * 此选项为 false 时，若在上一个命令尚未完成时发送命令则会抛出异常。
     * 此选项适合生产者使用，生产者可以使用单个链接并发的调用 put 进行放入消息，而不需要在并发 put 时使用多个链接。
     * 对于消费者却不太适用，原因是消费者大部分时间会阻塞在 reserve 状态，多个消费者应该使用多个链接。
     * 对于使用不同的 tube 和 watch tube 时，则不该依赖此选项。
	 */
	public function __construct($host='127.0.0.1', $port=11300, $options=[])
	{
        $this->autoReconnect = $options['autoReconnect'] ?? false;
        $this->reconnectDelay = $options['reconnectDelay'] ?? 2;
        $this->concurrent = $options['concurrent'] ?? false;
        $this->connectTimeout = $options['connectTimeout'] ?? 5;
        $this->connection = new AsyncTcpConnection("tcp://$host:$port");
	}

    /**
     * 连接到服务器
     * 
     * 在使用任何命令前，需先连接到服务器
     * 
     * @return Promise<bool>
     */
	public function connect()
	{
		if ($this->connected) {
			return new Success();
        }

        //连接超时设置
        $connectTimer = Timer::add($this->connectTimeout, function() {
            $this->connection->destroy();
            if ($this->connectDefer) {
                $this->connectDefer->fail(new BeanstalkException('Connect to beanstalkd timeout.'));
                $this->connectDefer = null;
            }
        }, [], false);

        $this->connection->onConnect = function() use (&$connectTimer) {
            if ($connectTimer) {
                Timer::del($connectTimer);
                $connectTimer = null;
            }

            $this->connected = true;
            $this->connectDefer = null;

            //重连后恢复相应 tube 的监听和使用
            if ($this->autoReconnect) {
                asyncCall(function() {
                    $pending = $this->pending;
                    $sending = $this->sending;
                    $this->pending = $this->sending = null;
    
                    if ($this->tubeUsed != 'default') {
                        yield $this->useTube($this->tubeUsed);
                    }
    
                    $watchDefault = false;
    
                    foreach ($this->watchTubes as $tube) {
                        if ($tube == 'default') {
                            $watchDefault = true;
                        } else {
                            yield $this->watch($tube);
                        }
                    }
    
                    if (!$watchDefault) {
                        yield $this->ignore('default');
                    }
    
                    if (is_callable($this->onConnectCallback)) {
                        call_user_func($this->onConnectCallback);
                        $this->onConnectCallback = null;
                    }

                    //恢复上次的命令执行
                    if ($sending) {
                        call_user_func_array([$this, 'send'], $sending)->onResolve(function($e, $v) use ($pending) {
                            $e ? $pending->fail($e) : $pending->resolve($v);
                        });
                    }
                });
            } else {
                if (is_callable($this->onConnectCallback)) {
                    call_user_func($this->onConnectCallback);
                    $this->onConnectCallback = null;
                }
            }
        };
        
        $this->connection->onError = function($connection, $code, $message) use (&$connectTimer) {
            if ($connectTimer) {
                Timer::del($connectTimer);
                $connectTimer = null;
            }

            echo "Connection Error: [$code] $message\n";

            if (is_callable($this->onErrorCallback)) {
                call_user_func($this->onErrorCallback, $connection, $code, $message);
                $this->onErrorCallback = null;
            }
        };

        $this->connection->onClose = function() use (&$connectTimer) {
            if ($connectTimer) {
                Timer::del($connectTimer);
                $connectTimer = null;
            }

            echo "Disconnected.\n";
            $this->connected = false;

            if ($this->autoReconnect) {
                //自动重连时等待并尝试重连
                echo "Reconnect after {$this->reconnectDelay} seconds.\n";
                delay($this->reconnectDelay*1000)->onResolve(function() {
                    $this->connect();
                });
            } elseif ($this->pending) {
                //不自动重连时，断开连接之前的动作抛出异常
                $this->pending->fail(new BeanstalkException('Disconnected.'));
                $this->pending = $this->connectDefer = null;
            }

            if (is_callable($this->onCloseCallback)) {
                call_user_func($this->onCloseCallback);
                $this->onCloseCallback = null;
            }
        };

        $defer = $this->connectDefer ?? new Deferred();
        
        $this->onConnectCallback = function() use ($defer) {
            $defer->resolve();
        };

        if (!$this->autoReconnect) {
            $this->onErrorCallback = function($connection, $code, $message) use ($defer) {
                $defer->fail(new BeanstalkException($message, $code));
            };
        }

        $this->connection->connect();
        $this->connectDefer = $defer;

        return $defer->promise();
    }
    
    /**
     * 主动关闭连接
     * 
     * 注意
     * 主动关闭连接后再重新调用 connect() 连接成功并不会恢复之前的监控与 reserve 命令状态。
     * 主动关闭连接后并不会触发重新连接。
     *
     * @return Promise
     */
    public function close()
    {
        if (!$this->connected) {
            return new Success();
        }

        $defer = new Deferred();

        $reconnect = $this->autoReconnect;
        $this->autoReconnect = false;

        $this->onCloseCallback = function() use ($defer, $reconnect) {
            $this->tubeUsed = 'default';
            $this->watchTubes = ['default'];
            $this->sending = null;
            $this->autoReconnect = $reconnect;
            $defer->resolve();
        };

        //尽量使用 quit 命令断开连接
        if ($this->pending || $this->connection->send("quit\r\n") === false) {
            $this->connection->destroy();
        }

        return $defer->promise();
    }

    /**
     * 向队列中存入消息
     *
     * @param string $data 消息内容
     * @param int $pri 消息优先级，数字越小越优先，范围是2^32正整数（0-4,294,967,295)，默认为 1024
     * @param int $delay 消息延迟秒数，默认0为不延迟
     * @param int $ttr 消息处理时间，当消息被 RESERVED 后，超出此时间状态未发生变更，则重新回到 ready 队列，最小值为1
     * @return Promise<int> 成功返回消息ID
     */
	public function put($data, $pri=self::DEFAULT_PRI, $delay=0, $ttr=self::DEFAULT_TTR)
	{
        $cmd = sprintf("put %d %d %d %d\r\n%s", $pri, $delay, $ttr, strlen($data), $data);

        return call(function() use ($cmd) {
            $res = yield $this->send($cmd);
    
            if ($res['status'] == 'INSERTED') {
                return $res['meta'][0];
            } else {
                throw new BeanstalkException($res['status']);
            }
        });
	}

    /**
     * 使用指定 Tube
     * 
     * 用于生产者。
     * 指定 put 命令存入消息的 tube 名称，不指定时默认为 default。
     *
     * @param string $tube
     * @return Promise<bool>
     */
	public function useTube($tube)
	{
        return call(function() use ($tube) {
            $ret = yield $this->send(sprintf('use %s', $tube));
            if ($ret['status'] == 'USING' && $ret['meta'][0] == $tube) {
                $this->tubeUsed = $tube;
                return true;
            } else {
                throw new BeanstalkException($ret['status'].": Use tube $tube failed.");
            }
        });
	}

    /**
     * 取出（预订）消息
     *
     * @param int $timeout 取出消息的超时时间秒数，默认不超时，若设置了时间，当达到时间仍没有消息则返回 TIMED_OUT 异常消息
     * @return Promise<array> 返回数组包含以下字段，id: 消息ID, body: 消息内容, bytes: 消息内容长度
     */
	public function reserve($timeout=null)
	{
        $cmd = isset($timeout) ? sprintf('reserve-with-timeout %d', $timeout) : 'reserve';
        
        return call(function() use ($cmd) {
            $res = yield $this->send($cmd, null, true, 1);
            if ($res['status'] == 'RESERVED') {
                list($id, $bytes) = $res['meta'];
                return [
                    'id' => $id,
                    'body' => $res['body'],
                    'bytes' => $bytes
                ];
            } else {
                throw new BeanstalkException($res['status']);
            }
        });
	}

    /**
     * 删除消息
     *
     * @param int $id
     * @return Promise<bool>
     */
	public function delete($id)
	{
		return $this->send(sprintf('delete %d', $id), 'DELETED');
	}

    /**
     * 将消息重新放回 ready 队列
     *
     * @param int $id 消息ID
     * @param int $pri 消息优先级，与 put 一致
     * @param int $delay 消息延迟时间，与 put 一致
     * @return Promise<bool>
     */
	public function release($id, $pri=self::DEFAULT_PRI, $delay=0)
	{
		return $this->send(sprintf('release %d %d %d', $id, $pri, $delay), 'RELEASED');
	}

    /**
     * 将消息放入 Buried（失败）队列
     *
     * 放入 buried 队列后的消息可以由 kick 唤醒
     * 
     * @param int $id
     * @param int $pri kick 出时的优先级
     * @return void
     */
	public function bury($id, $pri=self::DEFAULT_PRI)
	{
		return $this->send(sprintf('bury %d %d', $id, $pri), 'BURIED');
	}

    /**
     * 延续消息处理时间
     * 
     * 在处理期间的消息可以通过 touch 延迟 ttr 时间，当调用 touch 后，消息的 ttr 的时间将从头算起。
     *
     * @param int $id
     * @return void
     */
	public function touch($id)
	{
		return $this->send(sprintf('touch %d', $id), 'TOUCHED');
	}

    /**
     * 监控指定 tube 的消息
     * 
     * 默认监控 default 的 tube 消息，调用此方法将添加更多的 tube 到监控中。
     * 可以使用 ignore 来取消指定 tube 的监控，如果连接断开后监控将重置为 default。
     * 如果 autoReconnect 设为 true，则自动重连成功后会继续保持之前的监控。
     *
     * @param string $tube
     * @return Promise<int> 返回当前监控Tube数量
     */
	public function watch($tube)
	{
        return call(function() use ($tube) {
            $res = yield $this->send(sprintf('watch %s', $tube));

            if ($res['status'] == 'WATCHING') {
                if (!in_array($tube, $this->watchTubes)) {
                    $this->watchTubes[] = $tube;
                }
                return $res['meta'][0];
            } else {
                throw new BeanstalkException($res['status']);
            }
        });
	}

    /**
     * 忽略指定 Tube 的监控
     * 
     * 忽略后的 tube 将不再获取其消息，同 watch，当 autoReconnect 为 true 时，则自动重连后将会继续忽略之前的设定。
     *
     * @param string $tube
     * @return Promise<int> 返回剩余监控 tube 数量
     */
	public function ignore($tube)
	{
        return call(function() use ($tube) {
            $res = yield $this->send(sprintf('ignore %s', $tube));

            if ($res['status'] == 'WATCHING') {
                ArrayUtil::removeElement($this->watchTubes, $tube);
                return $res['meta'][0];
            } else {
                throw new BeanstalkException($res['status']);
            }
        });
	}

    /**
     * 检查指定的消息
     * 
     * 获取指定的消息内容，但不会改变消息状态
     *
     * @param int $id
     * @return Promise<array> 返回内容与 reserve 一致
     */
	public function peek($id)
	{
		return $this->peekRead(sprintf('peek %d', $id));
	}

    /**
     * 检查就绪队列中的下一条消息
     *
     * @return Promise<array>
     */
	public function peekReady()
	{
		return $this->peekRead('peek-ready');
	}

    /**
     * 检查延迟队列中的下一条消息
     * 
     * @return Promise<array>
     */
	public function peekDelayed()
	{
		return $this->peekRead('peek-delayed');
	}

    /**
     * 检查失败队列中的下一条消息
     * 
     * @return Promise<array>
     */
	public function peekBuried()
	{
		return $this->peekRead('peek-buried');
	}

    /**
     * 读取 peek 相应命令的响应
     * 
     * @return Promise<array>
     */
	protected function peekRead($cmd)
	{
        return call(function() use ($cmd) {
            $res = yield $this->send($cmd, null, true, 1);

            if ($res['status'] == 'FOUND') {
                list($id, $bytes) = $res['meta'];
                return [
                    'id' => $id,
                    'body' => $res['body'],
                    'bytes' => $bytes
                ];
            } else {
                throw new BeanstalkException($res['status']);
            }
        });
	}

    /**
     * 将失败队列中的消息重新踢致就绪或延迟队列中
     *
     * @param int $bound 踢出的消息数量上限
     * @return Promise<int> 返回实际踢出的消息数量
     */
	public function kick($bound)
	{
        return call(function() use ($bound) {
            $res = yield $this->send(sprintf('kick %d', $bound));

            if ($res['status'] == 'KICKED') {
                return $res['meta'][0];
            } else {
                throw new BeanstalkException($res['status']);
            }
        });
	}

    /**
     * 将指定的消息踢出到就绪或延迟队列中
     *
     * @param int $id
     * @return Promise<bool>
     */
	public function kickJob($id)
	{
		return $this->send(sprintf('kick-job %d', $id), 'KICKED');
	}

    /**
     * 统计消息相关信息
     *
     * @param int $id
     * @return Promise<array>
     */
	public function statsJob($id)
	{
		return $this->statsRead(sprintf('stats-job %d', $id));
	}

    /**
     * 统计 Tube 相关信息
     *
     * @param string $tube
     * @return Promise<array>
     */
	public function statsTube($tube)
	{
		return $this->statsRead(sprintf('stats-tube %s', $tube));
	}

    /**
     * 返回服务器相关信息
     *
     * @return Promise<array>
     */
	public function stats()
	{
		return $this->statsRead('stats');
	}

    /**
     * 列出所在存在的 Tube
     * 
     * @return Promise<array>
     */
	public function listTubes()
	{
		return $this->statsRead('list-tubes');
	}

    /**
     * 检查当前客户端正在使用的 tube
     *
     * @return Promise<string>
     */
	public function listTubeUsed()
	{
        return call(function() {
            $res = yield $this->send('list-tube-used');
            if ($res['status'] == 'USING') {
                return $res['meta'][0];
            } else {
                throw new BeanstalkException($res['status']);
            }
        });
	}

    /**
     * 列出当前监控的 tube
     *
     * @return Promise<array>
     */
	public function listTubesWatched()
	{
		return $this->statsRead('list-tubes-watched');
	}

    /**
     * 读取返回的统计或列表信息
     *
     * @param string $cmd
     * @return Promise<array>
     */
	protected function statsRead($cmd)
	{
        return call(function() use ($cmd) {
            $res = yield $this->send($cmd, null, true, 0);

            if ($res['status'] == 'OK') {
                $body = rtrim($res['body']);
                $data = array_slice(explode("\n", $body), 1);
                $result = [];
    
                foreach ($data as $row) {
                    if ($row[0] == '-') {
                        $value = substr($row, 2);
                        $key = null;
                    } else {
                        $pos = strpos($row, ':');
                        $key = substr($row, 0, $pos);
                        $value = substr($row, $pos+2);
                    }
                    if (is_numeric($value)) {
                        $value = (int)$value == $value ? (int)$value : (float)$value;
                    }
                    isset($key) ? $result[$key] = $value : array_push($result, $value);
                }
                return $result;
            } else {
                throw new BeanstalkException($res['status']);
            }
        });
	}

    /**
     * 暂停指定 Tube 的消息分发直至指定的延迟时间
     *
     * @param string $tube 要暂停的 Tube
     * @param int $delay 延迟时间秒数
     * @return Promise<bool>
     */
	public function pauseTube($tube, $delay)
	{
		return $this->send(sprintf('pause-tube %s %d', $tube, $delay), 'PAUSED');
	}

    /**
     * 发送命令并接收数据
     *
     * @param string $cmd
     * @param string $status 确认成功匹配状态
     * @param bool $chunk 是否大数据分块模式
     * @param int $bytesMetaPos 大数据分块长度数据所在位置，即状态如 "RESERVED" 后的描述，从0开始
     * @return Promise<Array>
     */
	protected function send($cmd, $status=null, $chunk=false, $bytesMetaPos=0)
	{
		if (!$this->connected) {
			throw new BeanstalkException('No connecting found while writing data to socket.');
        }
        
        //前一个命令尚未完成时的并发处理
        if ($this->pending) {
            if ($this->concurrent) {
                $args = func_get_args();
                $defer = new Deferred;
                $this->pending->promise()->onResolve(function($e) use ($defer, $args) {
                    call_user_func_array([$this, 'send'], $args)->onResolve(function($e, $v) use ($defer) {
                        $e ? $defer->fail($e) : $defer->resolve($v);
                    });
                });
                return $defer->promise();
            } else {
                throw new BeanstalkException('Cannot send command before previous command finished.');
            }
        }
        
        $defer = new Deferred;

        $data = [
            'status' => '',
            'meta' => [],
            'body' => ''
        ];

        $this->connection->onMessage = function($connection, $recv) use ($defer, $status, $chunk, &$data, $bytesMetaPos) {
            if ($this->debug) {
                $this->wrap($recv, false);
            }

            //首次读取头部，多次则追加数据直到达到指定字节数为止
            if ($data['status'] == '') {
                $metaEnd = strpos($recv, "\r\n");
                $meta = explode(' ', substr($recv, 0, $metaEnd));
                $data['status'] = array_shift($meta);
                $data['meta'] = $meta;
                $data['body'] = $chunk ? substr($recv, $metaEnd+2, $meta[$bytesMetaPos]) : substr($recv, $metaEnd+2);
            } else {
                $data['body'] .= substr($recv, 0, $data['meta'][$bytesMetaPos]-strlen($data['body']));
            }

            if (!$chunk || strlen($data['body']) == $data['meta'][$bytesMetaPos]) {
                $connection->onMessage = $this->pending = $this->sending = null;
                if ($status !== null) {
                    //消息状态确认
                    if ($data['status'] != $status) {
                        $defer->fail(new BeanstalkException($data['status']));
                    } else {
                        $defer->resolve();
                    }
                } else {
                    $defer->resolve($data);
                }
            }
        };

        //记录发送中的调用参数以供失败重连后继续处理
        if ($this->autoReconnect) {
            $this->sending = func_get_args();
        }

        //发送命令 
        $cmd .= "\r\n";

		if ($this->debug) {
			$this->wrap($cmd, true);
        }

        $this->connection->send($cmd);
        $this->pending = $defer;

        return $defer->promise();
	}

	protected function wrap($output, $out)
	{
        $white = "\033[47;30m";
        $end = "\033[0m";
        if ($out) {
            echo "\r\n{$white}send{$end}>>------\r\n$output\r\n---------->>\r\n";
        } else {
            echo "\r\n{$white}recv{$end}<<------\r\n$output\r\n----------<<\r\n";
        }
	}

}