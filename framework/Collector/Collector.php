<?php

namespace Framework\Collector;

use Amp\Deferred;
use Channel\Client;
use Workerman\Timer;
use Workerman\Worker;
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
            $config = require BASE_DIR.'/config/collect.php';

            if (!$config['enable']) {
                return false;
            }

            $id = uniqid();

            $workers = getApp()->getWorkers();
            $countDown = 0;
            foreach ($workers as $worker) {
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
                Worker::log("[Collector] Worker {$worker->name}[{$worker->id}] received $event response");

                $response[] = $result;
                $countDown--;

                if ($countDown == 0) {
                    Timer::del($timerId);
                    Client::unsubscribe($event);
                    $defer->resolve($response);
                    Worker::log("[Collector] ===== $event Finished =====");
                }
            });

            Worker::log("[Collector] Worker {$worker->name}[{$worker->id}] request $event");
            Client::publish(self::class, $event);

            return $defer->promise();
        });
    }

}