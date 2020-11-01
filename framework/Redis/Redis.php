<?php

namespace Framework\Redis;

use Amp\Deferred;
use Workerman\Redis\Client;

class Redis
{

    private $redis;

    public function __construct($host, $port=6379)
    {
        $this->redis = new Client("redis://$host:$port");
    }

    public function __call($name, $args)
    {
        $defer = new Deferred;

        $args[] = function($result) use ($defer) {
            $defer->resolve($result);
        };

        call_user_func_array([$this->redis, $name], $args);

        return $defer->promise();
    }

}
