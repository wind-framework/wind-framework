<?php

namespace Framework\Queue;

use function Amp\call;

use Framework\Base\Config;

class Queue
{

    private static $queueDrivers = [];

    public static function put($queue, Job $job, $delay=0)
    {
        $config = di()->get(Config::class)->get("queue.$queue");

        if (!$config) {
            throw new \Exception("Not found config for queue'$queue'.");
        }

        /** @var DriverInterface $driver */
        $driver = new $config['driver']($config);

        return call(function() use ($driver, $job, $delay) {
            yield $driver->connect();

            $message = new Message($job);
            yield $driver->push($message, $delay);
            yield $driver->close();
        });
    }

    private static function getDriver()
    {

    }

}
