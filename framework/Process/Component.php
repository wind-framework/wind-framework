<?php

namespace Framework\Process;

use Amp\Loop;
use Workerman\Worker;

class Component implements \Framework\Base\Component
{

    public static function start()
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
                    echo "Process $class starting..\n";
                    Loop::defer([$process, 'run']);
                };
            }
        }
    }

}