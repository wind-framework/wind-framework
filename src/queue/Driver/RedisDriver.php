<?php

namespace Wind\Queue\Driver;

use function Amp\call;
use Wind\Queue\Queue;
use Wind\Redis\Redis;

use Wind\Queue\Message;
use Wind\Utils\StrUtil;

class RedisDriver extends Driver
{

    /**
     * @var Redis
     */
    private $redis;
    private $btimeout = 10;

    private $keysReady = [];
    private $keyReserved;
    private $keyDelay;
    private $keyFail;

    private $uniq;
    private $autoId = 0;

    public function __construct($config)
    {
        $this->redis = di()->make(Redis::class);

        $rk = $config['key'].':ready';
        $this->keysReady = [
            Queue::PRI_HIGH  => $rk.':high',
            Queue::PRI_NORMAL  => $rk.':normal',
            Queue::PRI_LOW  => $rk.':low'
        ];
        $this->keyReserved = $config['key'].':reserved';
        $this->keyDelay = $config['key'].':delay';
        $this->keyFail = $config['key'].':fail';

        //轮询需要有间隔主要作用于延迟队列的转移，在有多个并发时每个并发都有可能进行转移处理，理想情况下每秒都有协程处理到轮询。
        //所以并发多时，适当的增加轮询可间隔可以减少性能浪费
        $processes = $config['processes'] ?? 1;
        $concurrent = $config['concurrent'] ?? 1;
        $concurrent *= $processes;

        if ($concurrent < $this->btimeout) {
            $this->btimeout = $concurrent;
        }

        $this->uniq = StrUtil::randomString(8);
    }
    
    public function connect()
    {
        return $this->redis->connect();
    }

    public function push(Message $message, $delay)
    {
        if ($message->id === null) {
            $message->id = $this->uniq.'-'.(++$this->autoId);
        }

        $raw = self::serialize($message);

        if ($delay == 0) {
            $queue = $this->getPriorityKey($message->priority);
            return $this->redis->rPush($queue, $raw);
        } else {
            return $this->redis->zAdd($this->keyDelay, time()+$delay, $raw);
        }
    }

    public function pop()
    {
        return call(function() {
            yield $this->ready($this->keyDelay);
            yield $this->ready($this->keyReserved);

            list(, $raw) = yield $this->redis->blPop($this->keysReady, $this->btimeout);
            if ($raw === null) {
                return null;
            }

            $message = self::unserialize($raw);
            yield $this->redis->zAdd($this->keyReserved, time()+$message->job->ttr, $raw);

            return $message;
        });
    }

    public function ack(Message $message)
    {
        return $this->remove($message->raw);
    }

    public function fail(Message $message)
    {
        return call(function() use ($message) {
            if (yield $this->remove($message->raw)) {
                return yield $this->redis->rPush($this->keyFail, $message->raw);
            } else {
                return false;
            }
        });
    }

    public function release(Message $message, $delay)
    {
        return call(function() use ($message, $delay) {
            if (yield $this->remove($message->raw)) {
                $message->attempts++;
                $raw = self::serialize($message);
                return $this->redis->zAdd($this->keyDelay, time() + $delay, $raw);
            }
            return false;
        });
    }

    private function remove($raw)
    {
        return call(function() use ($raw) {
            return (yield $this->redis->zRem($this->keyReserved, $raw)) > 0;
        });
    }

    private function ready($queue)
    {
        return call(function() use ($queue) {
            $now = time();
            $options = ['LIMIT', 0, 128];
            if ($expires = yield $this->redis->zrevrangebyscore($queue, $now, '-inf', $options)) {
                foreach ($expires as $raw) {
                    if ((yield $this->redis->zRem($queue, $raw)) > 0) {
                        $message = self::unserialize($raw);
                        $key = $this->getPriorityKey($message->priority);
                        yield $this->redis->rPush($key, $raw);
                    }
                }
            }
        });
    }

    private function getPriorityKey($pri)
    {
        return $this->keysReady[$pri] ?? $this->keysReady[Queue::PRI_NORMAL];
    }

    private static function serialize(Message $message)
    {
        return \serialize([$message->id, $message->job, $message->attempts, $message->priority]);
    }

    private static function unserialize($raw)
    {
        list($id, $job, $attempts, $pri) = \unserialize($raw);
        $message = new Message($job, $id, $raw);
        $message->attempts = $attempts;
        $message->priority = $pri;
        return $message;
    }

}
