<?php

namespace Framework\Queue;

use Amp\Deferred;
use Framework\Utils\ArrayUtil;
use RuntimeException;
use function Amp\call;

use Workerman\Connection\AsyncTcpConnection;

class BeanstalkClient
{

	const DEFAULT_PRI = 60;
	const DEFAULT_TTR = 30;

	protected $config;
	protected $connection;
    protected $lastError = null;
    private $connected = false;

    private $tubeUsed = 'default';
    private $watchTubes = ['default'];

    private $onConnectCallback = null;
    private $onErrorCallback = null;

	public $debug = false;

	/**
	 * Swbeanstalk constructor.
	 *
	 * @param string $host
	 * @param int $port
	 * @param int $connectTimeout Connect timeout, -1 means never timeout.
	 * @param int $timeout Read, write timeout, -1 means never timeout.
	 */
	public function __construct($host='127.0.0.1', $port=11300, $connectTimeout=1, $timeout=-1)
	{
		$this->config = compact('host', 'port');
        $this->connection = new AsyncTcpConnection("tcp://$host:$port");

        $this->connection->onConnect = function(...$args) {
            $this->connected = true;
            if (is_callable($this->onConnectCallback)) {
                call_user_func_array($this->onConnectCallback, $args);
                $this->onConnectCallback = null;
            }

            //重连后恢复相应 tube 的监听和使用
            call(function() {
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
            });
        };
        
        $this->connection->onError = function($connection, $code, $message) {
            $this->connected = false;
            if (is_callable($this->onErrorCallback)) {
                call_user_func($this->onErrorCallback, $connection, $connection, $message);
                $this->onErrorCallback = null;
            }
        };

        $this->connection->onClose = function($connection) {
            $this->connected = false;
        };
	}

	public function connect()
	{
		if ($this->connected) {
			$this->connection->close();
        }

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

	public function put($data, $pri=self::DEFAULT_PRI, $delay=0, $ttr=self::DEFAULT_TTR)
	{
        $this->send(sprintf("put %d %d %d %d\r\n%s", $pri, $delay, $ttr, strlen($data), $data));

        return call(function() {
            $res = yield $this->recv();
    
            if ($res['status'] == 'INSERTED') {
                return $res['meta'][0];
            } else {
                $this->setError($res['status']);
                return false;
            }
        });
	}

	public function useTube($tube)
	{
        return call(function() use ($tube) {
            $this->send(sprintf('use %s', $tube));
            $ret = yield $this->recv();
            if ($ret['status'] == 'USING' && $ret['meta'][0] == $tube) {
                $this->tubeUsed = $tube;
                return true;
            } else {
                $this->setError($ret['status'], "Use tube $tube failed.");
                return false;
            }
        });
	}

	public function reserve($timeout=null)
	{
		if (isset($timeout)) {
			$this->send(sprintf('reserve-with-timeout %d', $timeout));
		} else {
			$this->send('reserve');
		}
        
        return call(function() {
            $res = yield $this->recv(true, 1);

            if ($res['status'] == 'RESERVED') {
                list($id, $bytes) = $res['meta'];
                return [
                    'id' => $id,
                    'body' => $res['body'],
                    'bytes' => $bytes
                ];
            } else {
                $this->setError($res['status']);
                return false;
            }
        });
	}

	public function delete($id)
	{
		return $this->sendv(sprintf('delete %d', $id), 'DELETED');
	}

	public function release($id, $pri=self::DEFAULT_PRI, $delay=0)
	{
		return $this->sendv(sprintf('release %d %d %d', $id, $pri, $delay), 'RELEASED');
	}

	public function bury($id)
	{
		return $this->sendv(sprintf('bury %d', $id), 'BURIED');
	}

	public function touch($id)
	{
		return $this->sendv(sprintf('touch %d', $id), 'TOUCHED');
	}

	public function watch($tube)
	{
        return call(function() use ($tube) {
            $this->send(sprintf('watch %s', $tube));
            $res = yield $this->recv();

            if ($res['status'] == 'WATCHING') {
                if (!in_array($tube, $this->watchTubes)) {
                    $this->watchTubes[] = $tube;
                }
                return $res['meta'][0];
            } else {
                $this->setError($res['status']);
                return false;
            }
        });
	}

	public function ignore($tube)
	{
        return call(function() use ($tube) {
            $this->send(sprintf('ignore %s', $tube));
            $res = yield $this->recv();

            if ($res['status'] == 'WATCHING') {
                ArrayUtil::removeElement($this->watchTubes, $tube);
                return $res['meta'][0];
            } else {
                $this->setError($res['status']);
                return false;
            }
        });
	}

	public function peek($id)
	{
		$this->send(sprintf('peek %d', $id));
		return $this->peekRead();
	}

	public function peekReady()
	{
		$this->send('peek-ready');
		return $this->peekRead();
	}

	public function peekDelayed()
	{
		$this->send('peek-delayed');
		return $this->peekRead();
	}

	public function peekBuried()
	{
		$this->send('peek-buried');
		return $this->peekRead();
	}

	protected function peekRead()
	{
        return call(function() {
            $res = yield $this->recv(true, 1);

            if ($res['status'] == 'FOUND') {
                list($id, $bytes) = $res['meta'];
                return [
                    'id' => $id,
                    'body' => $res['body'],
                    'bytes' => $bytes
                ];
            } else {
                $this->setError($res['status']);
                return false;
            }
        });
	}

	public function kick($bound)
	{
        $this->send(sprintf('kick %d', $bound));
        
        return call(function() {
            $res = yield $this->recv();

            if ($res['status'] == 'KICKED') {
                return $res['meta'][0];
            } else {
                $this->setError($res['status']);
                return false;
            }
        });
	}

	public function kickJob($id)
	{
		return $this->sendv(sprintf('kick-job %d', $id), 'KICKED');
	}

	public function statsJob($id)
	{
		$this->send(sprintf('stats-job %d', $id));
		return $this->statsRead();
	}

	public function statsTube($tube)
	{
		$this->send(sprintf('stats-tube %s', $tube));
		return $this->statsRead();
	}

	public function stats()
	{
		$this->send('stats');
		return $this->statsRead();
	}

	public function listTubes()
	{
		$this->send('list-tubes');
		return $this->statsRead();
	}

	public function listTubeUsed()
	{
        $this->send('list-tube-used');
        
        return call(function() {
            $res = yield $this->recv();
            if ($res['status'] == 'USING') {
                return $res['meta'][0];
            } else {
                $this->setError($res['status']);
                return false;
            }
        });
	}

	public function listTubesWatched()
	{
		$this->send('list-tubes-watched');
		return $this->statsRead();
	}

	protected function statsRead()
	{
        return call(function() {
            $res = yield $this->recv(true, 0);

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
                $this->setError($res['status']);
                return false;
            }
        });
	}

	public function pauseTube($tube, $delay)
	{
		return $this->sendv(sprintf('pause-tube %s %d', $tube, $delay), 'PAUSED');
	}

	protected function sendv($cmd, $status)
	{
        $this->send($cmd);
        
        return call(function() use ($status) {
            $res = yield $this->recv();

            if ($res['status'] != $status) {
                $this->setError($res['status']);
                return false;
            }
    
            return true;
        });
	}

	protected function send($cmd)
	{
		if (!$this->connected) {
			throw new \RuntimeException('No connecting found while writing data to socket.');
		}

		$cmd .= "\r\n";

		if ($this->debug) {
			$this->wrap($cmd, true);
		}

		return $this->connection->send($cmd);
	}

    /**
     * 接收数据
     *
     * @param bool $chunk 是否大数据分块模式
     * @param int $bytesMetaPos 大数据分块长度数据所在位置，即状态如 "RESERVED" 后的描述，从0开始
     * @return void
     */
	protected function recv($chunk=false, $bytesMetaPos=0)
	{
		if (!$this->connected) {
			throw new \RuntimeException('No connection found while reading data from socket.');
        }
        
        $defer = new Deferred;

        $data = [
            'status' => '',
            'meta' => [],
            'body' => ''
        ];

        $this->connection->onMessage = function($connection, $recv) use ($defer, $chunk, &$data, $bytesMetaPos) {
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
                $defer->resolve($data);
            }
        };

        return $defer->promise();
	}

	public function disconnect()
	{
		if ($this->connected) {
			$this->send('quit');
			$this->connection->close();
		}
	}

	protected function setError($status, $msg='')
	{
		$this->lastError = compact('status', 'msg');
	}

	public function getError()
	{
		if ($this->lastError) {
			$error = $this->lastError;
			$this->lastError = null;
			return $error;
		}
		return null;
	}

	protected function wrap($output, $out)
	{
		$line = $out ? '----->>' : '<<-----';
		echo "\r\n$line\r\n$output\r\n$line\r\n";
	}

}