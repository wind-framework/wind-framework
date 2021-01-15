<?php

namespace Wind\Cache;

use Wind\Redis\Redis;
use function Amp\call;

class RedisCache implements \Psr\SimpleCache\CacheInterface
{

    private $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function get($key, $defaultValue=null)
    {
        return call(function() use ($key, $defaultValue) {
            $data = yield $this->redis->get($key);
            return $data !== null ? unserialize($data) : $defaultValue;
        });
    }

    public function set($key, $value, $ttl=0)
    {
        $value = serialize($value);
        return $this->redis->set($key, $value, $ttl);
    }

    public function delete($key)
    {
        return $this->redis->del($key);
    }

    public function clear()
    {
        // TODO: Implement clear() method.
    }

    public function getMultiple($keys, $default = null)
    {
        return call(function() use ($keys, $default) {
            $arr = yield $this->redis->mGet($keys);
            $data = [];
            foreach ($keys as $i => $k) {
                $data[$k] = isset($arr[$i]) ? $arr[$i] : $default;
            }
            return $data;
        });
    }

    public function setMultiple($values, $ttl = null)
    {
        return $this->redis->mSet($values);
    }

    public function deleteMultiple($keys)
    {
        return call_user_func_array([$this->redis, 'del'], $keys);
    }

    public function has($key)
    {
        return $this->redis->exists($key);
    }
}