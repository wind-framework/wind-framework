<?php

namespace Framework\Queue;

use function Amp\call;

use Framework\Base\Config;

class Queue
{

    /**
     * Driver instances
     * @var \Framework\Queue\Driver\Driver[]
     */
    private static $queueDrivers = [];

    public static function put($queue, Job $job, $delay=0)
    {
        return call(function() use ($queue, $job, $delay) {
            $driver = yield self::getDriver($queue);
            $message = new Message($job);
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
