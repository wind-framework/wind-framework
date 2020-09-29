<?php

namespace App\Redis;

use Amp\Deferred;
use Protocols\Redis;
use Workerman\Redis\Client;
use function Amp\call as async;

class Cache
{

    private $def;

    /**
     * @return \Amp\Promise<Redis>
     */
    public function connect()
    {
        if ($this->def) {
            return $this->def->promise();
        }

        $this->def = new Deferred;

        $redis = new Client('redis://192.168.4.2:6379', [], function($success) use (&$redis) {
            if ($success) {
                echo "Redis 连接成功！\n";
                $this->def->resolve($redis);
            } else {
                echo "Redis 连接失败:".$redis->error()."！\n";
            }
        });

        return $this->def->promise();
    }

    public function get($key, $defaultValue=null)
    {
        return async(function() use ($key, $defaultValue) {
            $redis = yield $this->connect();
            $deferred = new Deferred;

            $redis->get($key, function ($result) use ($deferred, $defaultValue)  {
                $data = $result !== null ? unserialize($result) : $defaultValue;
                $deferred->resolve($data);
            });

            return $deferred->promise();
        });
    }

    public function set($key, $value, $ttl=0)
    {
        return async(function() use ($key, $value, $ttl) {
            $redis = yield $this->connect();
            $deferred = new Deferred;

            $value = serialize($value);
            $redis->set($key, $value, $ttl, function ($result) use ($deferred)  {
                $deferred->resolve();
            });

            return $deferred->promise();
        });
    }

}