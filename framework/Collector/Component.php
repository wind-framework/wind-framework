<?php

namespace Framework\Collector;

use Framework\Base\Config;
use Framework\Channel\Client;
use Framework\Channel\Server;
use Workerman\Worker;
use function Amp\delay;

class Component implements \Framework\Base\Component
{

    private static $ip;
    private static $port;

    /**
     * @var Worker
     */
    private static $currentWorker;

    public static function provide($app)
    {
        $config = di()->get(Config::class)->get('collector');

        if (!$config['enable']) {
            return;
        }

        //不指定 channel 时将启动自己的 channel
        if ($config['channel_server'] === null) {
	        $ip = '127.0.0.1';
	        $port = 2206;
	        new Server($ip, $port);
        } else {
            list($ip, $port) = explode(':', $config['channel_server']);
        }

        self::$ip = $ip;
        self::$port = $port;
    }

    public static function isEnable()
    {
        return !empty(self::$ip);
    }

    public static function start($worker)
    {
        if (!self::isEnable()) {
            return;
        }

        self::$currentWorker = $worker;

        //延迟启动，减少启动时 Server 未启动而重连的现象
	    yield delay(1000);

        Client::connect(self::$ip, self::$port);

        //收到请求后运行，并通过事件反馈请求
        Client::on(Collector::class, function($event) {
            list($collector) = explode('@', $event);
            $worker = self::getCurrentWorker();

            Worker::log("[Collector] Worker {$worker->name}[{$worker->id}] received $event request");

            /* @var $res Collector */
            $res = new $collector;
            $res->collect();
            $res->pid = posix_getpid();
            $res->workerId = $worker->id;
            $res->workerName = $worker->name;

            Client::publish($event, $res);
        });
    }

    public static function getCurrentWorker()
    {
        return self::$currentWorker;
    }

}