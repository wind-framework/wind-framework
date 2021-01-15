<?php

namespace Wind\Collector;

use Wind\Base\Channel;
use Psr\EventDispatcher\EventDispatcherInterface;
use Workerman\Worker;

class Component implements \Wind\Base\Component
{

    /**
     * @var Worker
     */
    private static $currentWorker;

    public static function provide($app)
    {
    }

    public static function start($worker)
    {
        self::$currentWorker = $worker;

        $channel = di()->get(Channel::class);

        //收到请求后运行，并通过事件反馈请求
        $channel->on(Collector::class, function($event) use ($channel) {
            list($collector) = explode('@', $event);
            $worker = self::getCurrentWorker();

            di()->get(EventDispatcherInterface::class)
                ->dispatch(new CollectorEvent($worker->id, $worker->name, $event, 'collect'));

            /* @var $res Collector */
            $res = new $collector;
            $res->collect();
            $res->pid = posix_getpid();
            $res->workerId = $worker->id;
            $res->workerName = $worker->name;

            $channel->publish($event, $res);
        });
    }

    public static function getCurrentWorker()
    {
        return self::$currentWorker;
    }

}