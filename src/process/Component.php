<?php

namespace Wind\Process;

use Wind\Base\Event\SystemError;
use Psr\EventDispatcher\EventDispatcherInterface;
use Workerman\Worker;

use function Amp\async;

class Component implements \Wind\Base\Component
{

    public static function provide($app)
    {
        $processes = $app->config->get('process');

        if ($processes) {
            foreach ($processes as $class) {
                /* @var $process Process */
                $process = $app->container->make($class);
                $isStatable = isset(class_uses($process)[Stateful::class]);

                $worker = new Worker();
                $worker->name = $process->name ?: $class;
                $worker->count = $process->count;
                $worker->onWorkerStart = static function ($worker) use ($process, $app, $isStatable) {
                    $app->startComponents($worker);

                    async([$process, 'run'])->onResolve(function($e) use ($app) {
                        if ($e) {
                            $app->container->get(EventDispatcherInterface::class)->dispatch(new SystemError($e));
                        }
                    });

                    $isStatable && $process->onGetState();
                };

                $app->addWorker($worker);

                //Statable count
                $isStatable && ProcessState::addStateCount($worker->count);
            }
        }
    }

    public static function start($worker) {
    }

}
