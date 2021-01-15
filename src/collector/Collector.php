<?php

namespace Wind\Collector;

use Amp\Deferred;
use Wind\Base\Channel;
use Wind\Base\Config;
use Wind\Utils\StrUtil;
use Psr\EventDispatcher\EventDispatcherInterface;
use Workerman\Timer;
use function Amp\call;

abstract class Collector
{

    public $pid;
    public $workerId;
    public $workerName;

    public abstract function collect();

    /**
     * 获取指定 Collector 类的结果
     *
     * @param string $collector
     * @return \Amp\Promise<$collector[]>
     */
    public static function get($collector)
    {
        return call(function() use ($collector) {
            $config = di()->get(Config::class)->get('collector');

            if (!$config['enable']) {
                return false;
            }

            $id = StrUtil::randomString(16);

            $countDown = 0;
            foreach (getApp()->workers as $worker) {
                $countDown += $worker->count;
            }

            $worker = Component::getCurrentWorker();
            $event = $collector.'@'.$id;

            $channel = di()->get(Channel::class);

            $defer = new Deferred();
            $response = [];

            //超时设置
            $timerId = Timer::add($config['timeout'], function() use (&$countDown, $event, $defer, &$response, $channel) {
                if ($countDown > 0) {
                    $channel->unsubscribe($event);
                    $defer->resolve($response);
                }
            }, [], false);

            //监听回应消息
            $channel->on($event, function($result) use (&$countDown, &$response, $id, $defer, $event, $worker, $timerId, $channel) {
                $eventDispatcher = di()->get(EventDispatcherInterface::class);
                $eventDispatcher->dispatch(new CollectorEvent($worker->id, $worker->name, $event, 'response'));

                $response[] = $result;

                if (--$countDown == 0) {
                    Timer::del($timerId);
                    $channel->unsubscribe($event);
                    $defer->resolve($response);
                    $eventDispatcher->dispatch(new CollectorEvent($worker->id, $worker->name, $event, 'finished'));
                }
            });

            di()->get(EventDispatcherInterface::class)->dispatch(new CollectorEvent($worker->id, $worker->name, $event, 'request'));
            $channel->publish(self::class, $event);

            return $defer->promise();
        });
    }

}