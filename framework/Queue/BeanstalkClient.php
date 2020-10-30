<?php

namespace Framework\Queue;

use Exception;
use Amp\Success;
use Amp\Deferred;
use RuntimeException;
use function Amp\call;

use function Amp\asyncCall;
use function Amp\delay;

use Framework\Utils\ArrayUtil;

use Workerman\Connection\AsyncTcpConnection;

class BeanstalkClient
{

	const DEFAULT_PRI = 60;
	const DEFAULT_TTR = 30;

    private $autoReconnect;
    
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
    public $pending = null;

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
	 * @param int $connectTimeout Connect timeout, -1 means never timeout.
	 * @param int $timeout Read, write timeout, -1 means never timeout.
     * @param bool $autoReconnect 是否断线自动重连，自动重连将会自动恢复以往正在发送的命令和动作
	 */
	public function __construct($host='127.0.0.1', $port=11300, $connectTimeout=1, $timeout=-1, $autoReconnect=false)
	{
        $this->autoReconnect = $autoReconnect;
        $this->connection = new AsyncTcpConnection("tcp://$host:$port");
	}

	public function connect()
	{
		if ($this->connected) {
			return new Success();
        }

        $this->connection->onConnect = function() {
            $this->connected = true;

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
        
        $this->connection->onError = function($connection, $code, $message) {
            echo "Connection Error: [$code] $message\n";
            if (is_callable($this->onErrorCallback)) {
                call_user_func($this->onErrorCallback, $connection, $code, $message);
                $this->onErrorCallback = null;
            }
        };

        $this->connection->onClose = function() {
            echo "Detect connection closed\n";
            $this->connected = false;

            if ($this->autoReconnect) {
                //自动重连时等待并尝试重连
                delay(2000)->onResolve(function() {
                    $this->connect();
                });
            } elseif ($this->pending) {
                //不自动重连时，断开连接之前的动作抛出异常
                $this->pending->fail(new RuntimeException('Disconnected.'));
                $this->pending = null;
            }

            if (is_callable($this->onCloseCallback)) {
                call_user_func($this->onCloseCallback);
                $this->onCloseCallback = null;
            }
        };

        $defer = new Deferred();
        
        $this->onConnectCallback = function() use ($defer) {
            $defer->resolve();
        };

        $this->onErrorCallback = function($connection, $code, $message) use ($defer) {
            $defer->fail(new RuntimeException($code, $message));
        };

        $this->connection->connect();

        return $defer->promise();
    }
    
    /**
     * 关闭连接
     *
     * @return \Amp\Promise
     */
    public function close()
    {
        if (!$this->connected) {
            return new Success();
        }

        $defer = new Deferred();

        $this->onCloseCallback = function() use ($defer) {
            $defer->resolve();
        };

        $this->connection->destroy();

        return $defer->promise();
    }

	public function put($data, $pri=self::DEFAULT_PRI, $delay=0, $ttr=self::DEFAULT_TTR)
	{
        $cmd = sprintf("put %d %d %d %d\r\n%s", $pri, $delay, $ttr, strlen($data), $data);

        return call(function() use ($cmd) {
            $res = yield $this->send($cmd);
    
            if ($res['status'] == 'INSERTED') {
                return $res['meta'][0];
            } else {
                throw new Exception($res['status']);
            }
        });
	}

	public function useTube($tube)
	{
        return call(function() use ($tube) {
            $ret = yield $this->send(sprintf('use %s', $tube));
            if ($ret['status'] == 'USING' && $ret['meta'][0] == $tube) {
                $this->tubeUsed = $tube;
                return true;
            } else {
                throw new Exception($ret['status'].": Use tube $tube failed.");
            }
        });
	}

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
                throw new Exception($res['status']);
            }
        });
	}

	public function delete($id)
	{
		return $this->send(sprintf('delete %d', $id), 'DELETED');
	}

	public function release($id, $pri=self::DEFAULT_PRI, $delay=0)
	{
		return $this->send(sprintf('release %d %d %d', $id, $pri, $delay), 'RELEASED');
	}

	public function bury($id)
	{
		return $this->send(sprintf('bury %d', $id), 'BURIED');
	}

	public function touch($id)
	{
		return $this->send(sprintf('touch %d', $id), 'TOUCHED');
	}

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
                throw new Exception($res['status']);
            }
        });
	}

	public function ignore($tube)
	{
        return call(function() use ($tube) {
            $res = yield $this->send(sprintf('ignore %s', $tube));

            if ($res['status'] == 'WATCHING') {
                ArrayUtil::removeElement($this->watchTubes, $tube);
                return $res['meta'][0];
            } else {
                throw new Exception($res['status']);
            }
        });
	}

	public function peek($id)
	{
		return $this->peekRead(sprintf('peek %d', $id));
	}

	public function peekReady()
	{
		return $this->peekRead('peek-ready');
	}

	public function peekDelayed()
	{
		return $this->peekRead('peek-delayed');
	}

	public function peekBuried()
	{
		return $this->peekRead('peek-buried');
	}

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
                throw new Exception($res['status']);
            }
        });
	}

	public function kick($bound)
	{
        return call(function() use ($bound) {
            $res = yield $this->send(sprintf('kick %d', $bound));

            if ($res['status'] == 'KICKED') {
                return $res['meta'][0];
            } else {
                throw new Exception($res['status']);
            }
        });
	}

	public function kickJob($id)
	{
		return $this->send(sprintf('kick-job %d', $id), 'KICKED');
	}

	public function statsJob($id)
	{
		return $this->statsRead(sprintf('stats-job %d', $id));
	}

	public function statsTube($tube)
	{
		return $this->statsRead(sprintf('stats-tube %s', $tube));
	}

	public function stats()
	{
		return $this->statsRead('stats');
	}

	public function listTubes()
	{
		return $this->statsRead('list-tubes');
	}

	public function listTubeUsed()
	{
        return call(function() {
            $res = yield $this->send('list-tube-used');
            if ($res['status'] == 'USING') {
                return $res['meta'][0];
            } else {
                throw new Exception($res['status']);
            }
        });
	}

	public function listTubesWatched()
	{
		return $this->statsRead('list-tubes-watched');
	}

	protected function statsRead($cmd)
	{
        return call(function() use ($cmd) {
            $res = yield $this->send($cmd, null, true, 0);

            if ($res['status'] == 'OK') {
                $data = array_slice(explode("\n", $res['body']), 1);
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
                throw new Exception($res['status']);
            }
        });
	}

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
			throw new \RuntimeException('No connecting found while writing data to socket.');
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
                        $defer->fail(new \Exception($data['status']));
                    } else {
                        $defer->resolve();
                    }
                } else {
                    $defer->resolve($data);
                }
            }
        };

        //记录发送中的调用参数
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

	public function disconnect()
	{
		if ($this->connected) {
			$this->send('quit');
			$this->connection->close();
		}
	}

	protected function wrap($output, $out)
	{
		$line = $out ? '----->>' : '<<-----';
		echo "\r\n$line\r\n$output\r\n$line\r\n";
	}

}