<?php

namespace Framework\Queue;

use function Amp\call;

use Framework\Base\Config;

class Queue
{

    private static $queueDrivers = [];

    public static function put($queue, Job $job, $delay=0)
    {
        if (!isset(self::$queueDrivers[$queue])) {
            $config = di()->get(Config::class)->get("queue.$queue");

            if (!$config) {
                throw new \Exception("Not found config for queue'$queue'.");
            }
    
            /** @var DriverInterface $driver */
            $driver = self::$queueDrivers[$queue] = new $config['driver']($config);
        }

        $driver = self::$queueDrivers[$queue];

        return call(function() use ($driver, $job, $delay) {
            yield $driver->connect();

            $message = new Message($job);
            return yield $driver->push($message, $delay);
            // yield $driver->close();
        });
    }

    private static function getDriver()
    {

    }

}
