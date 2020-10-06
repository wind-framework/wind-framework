<?php

namespace Framework\Process;

use Amp\Loop;
use Workerman\Worker;

class Component implements \Framework\Base\Component
{

    public static function provide()
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
                $worker->onWorkerStart = function () use ($process, $class) {
                    Worker::log("Process $class starting..");
                    Loop::defer([$process, 'run']);
                };
            }
        }
    }

    public static function start() {
    }

}