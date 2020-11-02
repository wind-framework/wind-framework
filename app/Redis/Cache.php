<?php

namespace App\Redis;

use Framework\Redis\Redis;
use Psr\Container\ContainerInterface;

use function Amp\call as async;

class Cache
{

    private $redis;

    public function __construct(ContainerInterface $container)
    {
        $this->redis = $container->get(Redis::class);
    }

    public function get($key, $defaultValue=null)
    {
        return async(function() use ($key, $defaultValue) {
            $data = yield $this->redis->get($key);
            return $data !== null ? unserialize($data) : $defaultValue;
        });
    }

    public function set($key, $value, $ttl=0)
    {
        $value = serialize($value);
        return $this->redis->set($key, $value, $ttl);
    }

}