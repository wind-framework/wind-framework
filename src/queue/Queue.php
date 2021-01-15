<?php

namespace Wind\Queue;

use function Amp\call;

use Wind\Base\Config;

class Queue
{

    //消息基础优先级
    const PRI_HIGH = 0;
    const PRI_NORMAL = 1;
    const PRI_LOW = 2;

    /**
     * Driver instances
     * @var \Wind\Queue\Driver\Driver[]
     */
    private static $queueDrivers = [];

    public static function put($queue, Job $job, $delay=0, $priority=self::PRI_NORMAL)
    {
        return call(function() use ($queue, $job, $delay, $priority) {
            $driver = yield self::getDriver($queue);
            $message = new Message($job);
            $message->priority = $priority;
            return yield $driver->push($message, $delay);
        });
    }

    private static function getDriver($queue)
    {
        return call(function() use ($queue) {
            if (isset(self::$queueDrivers[$queue])) {
                return self::$queueDrivers[$queue];
            }

            $config = di()->get(Config::class)->get("queue.$queue");

            if (!$config) {
                throw new \Exception("Not found config for queue'$queue'.");
            }
    
            /** @var DriverInterface $driver */
            $driver = self::$queueDrivers[$queue] = new $config['driver']($config);
            yield $driver->connect();

            return self::$queueDrivers[$queue] = $driver;
        });
    }

}
