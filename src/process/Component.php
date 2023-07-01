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
                /** @var Process $process */
                $process = $app->container->make($class);

                if (!$process instanceof Process) {
                    throw new \RuntimeException("$class is not instance of Process.");
                }

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
                        async(static fn() => $process->run())->catch(static fn($e) => throw $e);
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
