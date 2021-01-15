<?php

namespace Wind\Process;

use Wind\Base\Config;
use Wind\Base\Event\SystemError;
use Psr\EventDispatcher\EventDispatcherInterface;
use Workerman\Worker;
use function Amp\call;

class Component implements \Wind\Base\Component
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
                    $app->startComponents($worker);
                    call([$process, 'run'])->onResolve(function($e) use ($app) {
                        if ($e) {
                            $app->container->get(EventDispatcherInterface::class)->dispatch(new SystemError($e));
                        }
                    });
                };

                $app->addWorker($worker);
            }
        }
    }

    public static function start($worker) {
    }

}