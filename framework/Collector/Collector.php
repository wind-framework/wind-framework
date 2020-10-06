<?php

namespace Framework\Collector;

use Amp\Deferred;
use Channel\Client;
use Framework\Base\Application;
use Workerman\Timer;
use function Amp\call;

abstract class Collector
{

    public $pid;
    public $workerId;
    public $workerName;

    public abstract function collect();

    public static function get($collector)
    {
        return call(function() use ($collector) {
            $config = require BASE_DIR.'/config/collect.php';

            if (!$config['enable']) {
                return false;
            }

            $id = uniqid();
            $workerInfo = Application::getInstance()->getWorkerInfo();
            $countDown = $workerInfo['count'];
            $event = $collector.'@'.$id;

            echo "Publish ".self::class." -> $event\n";
            Client::publish(self::class, $event);

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
            Client::on($event, function($result) use (&$countDown, &$response, $id, $defer, $event, $workerInfo, $timerId) {
                echo "Worker {$workerInfo['id']} received $event response\n";

                $response[] = $result;
                $countDown--;

                if ($countDown == 0) {
                    Timer::del($timerId);
                    Client::unsubscribe($event);
                    $defer->resolve($response);
                    echo "========Finished========\n";
                }
            });

            return $defer->promise();
        });
    }

}