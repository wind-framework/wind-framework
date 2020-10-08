<?php

namespace Framework\Process;

use Amp\Loop;
use Workerman\Worker;

class Component implements \Framework\Base\Component
{

    public static function provide($app)
    {
        $config = BASE_DIR.'/config/process.php';
        if (!is_file($config)) return;

        $processes = require $config;

        if ($processes) {
            foreach ($processes as $class) {
                /* @var $process Process */
                $process = new $class;
                $worker = new Worker();
                $worker->name = $process->name ?: $class;
                $worker->count = $process->count;
                $worker->onWorkerStart = static function ($worker) use ($process, $class, $app) {
                    Worker::log("Process $class starting..");
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