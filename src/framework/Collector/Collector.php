<?php

namespace Framework\Collector;

use Amp\Deferred;
use Framework\Base\Config;
use Psr\EventDispatcher\EventDispatcherInterface;
use Workerman\Timer;
use Workerman\Worker;
use function Amp\call;
use Framework\Utils\StrUtil;
use Framework\Channel\Client;

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

            $defer = new Deferred();
            $response = [];

            //超时设置
            $timerId = Timer::add($config['timeout'], function() use (&$countDown, $event, $defer, &$response) {
                if ($countDown > 0) {
                    Client::unsubscribe($event);
                    $defer->resolve($response);
                }
            }, [], false);

            //监听回应消息
            Client::on($event, function($result) use (&$countDown, &$response, $id, $defer, $event, $worker, $timerId) {
                $eventDispatcher = di()->get(EventDispatcherInterface::class);
                $eventDispatcher->dispatch(new CollectorEvent($worker->id, $worker->name, $event, 'response'));

                $response[] = $result;

                if (--$countDown == 0) {
                    Timer::del($timerId);
                    Client::unsubscribe($event);
                    $defer->resolve($response);
                    $eventDispatcher->dispatch(new CollectorEvent($worker->id, $worker->name, $event, 'finished'));
                }
            });

            di()->get(EventDispatcherInterface::class)->dispatch(new CollectorEvent($worker->id, $worker->name, $event, 'request'));
            Client::publish(self::class, $event);

            return $defer->promise();
        });
    }

}