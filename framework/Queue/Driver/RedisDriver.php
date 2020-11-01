<?php

namespace Framework\Queue\Driver;

use Amp\Success;
use Framework\Redis\Redis;
use Framework\Queue\Message;
use Framework\Queue\DriverInterface;

use function Amp\call;

class RedisDriver implements DriverInterface
{

    private $redis;

    private $keyReady;
    private $keyReserved;
    private $keyDelay;
    private $keyFail;

    public function __construct($config)
    {
        $this->redis = new Redis($config['host'], $config['port']);
        $this->keyReady = $config['key'].'.ready';
        $this->keyReserved = $config['key'].'reserved';
        $this->keyDelay = $config['key'].'.delay';
        $this->keyFail = $config['key'].'.fail';
    }
    
    public function connect()
    {
        return $this->redis->connect();
    }

    public function close()
    {
        return $this->redis->close();
    }

    public function push(Message $message, $delay=0)
    {
        $raw = serialize($message);

        if ($delay == 0) {
            return $this->redis->rPush($this->keyReady, $raw);
        } else {
            return $this->redis->zAdd($this->keyDelay, time()+$delay, $raw);
        }
    }

    public function pop()
    {
        return call(function() {
            list(, $raw) = yield $this->redis->blPop($this->keyReady, 0);
            if ($raw === null) {
                return null;
            }
            $message = unserialize($raw);
            // yield $this->redis->zAdd($this->keyReserved)
            return $message;
        });
    }

}
