<?php

namespace Wind\Process;

use Wind\Base\Event\SystemError;
use Psr\EventDispatcher\EventDispatcherInterface;
use Workerman\Worker;
use function Amp\call;

class Component implements \Wind\Base\Component
{

    public static function provide($app)
    {
        $processes = $app->config->get('process');

        if ($processes) {
            foreach ($processes as $class) {
                /* @var $process Process */
                $process = $app->container->make($class);

                $isStateful = method_exists($process, 'onGetState') && method_exists($process, 'getState');
                $isMergedProcess = $process instanceof MergedProcess;

                $worker = new Worker();
                $worker->name = $process->name ?: $class;
                !$isMergedProcess && $worker->count = $process->count;

                $worker->onWorkerStart = static function ($worker) use ($process, $app, $isStateful, $isMergedProcess) {
                    $app->startComponents($worker);

                    if ($isMergedProcess) {
                        $process->run();
                    } else {
                        call([$process, 'run'])->onResolve(function($e) use ($app) {
                            if ($e) {
                                $app->container->get(EventDispatcherInterface::class)->dispatch(new SystemError($e));
                            }
                        });
                    }

                    $isStateful && $process->onGetState();
                };

                $app->addWorker($worker);

                $isStateful && ProcessState::addStateCount($worker->count);
            }
        }
    }

    public static function start($worker) {
    }

}
