<?php

namespace Framework\Process;

use Amp\Loop;
use Framework\Base\Config;
use Workerman\Worker;

class Component implements \Framework\Base\Component
{

    public static function provide($app)
    {
        $config = $app->container->get(Config::class);
        $processes = $config->get('process');

        if ($processes) {
            foreach ($processes as $class) {
                /* @var $process Process */
                $process = $app->container->make($class);
                $worker = new Worker();
                $worker->name = $process->name ?: $class;
                $worker->count = $process->count;
                $worker->onWorkerStart = static function ($worker) use ($process, $class, $app) {
                    Worker::log("Process $class started.");
                    $app->startComponents($worker);
                    Loop::defer([$process, 'run']);
                };

                $app->addWorker($worker);
            }
        }
    }

    public static function start($worker) {
    }

}