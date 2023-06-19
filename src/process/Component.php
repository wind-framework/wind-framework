<?php

namespace Wind\Process;

use Workerman\Worker;

use function Amp\asyncCall;

class Component implements \Wind\Base\Component
{

    public static function provide($app)
    {
        $processes = $app->config->get('process');

        if ($processes) {
            foreach ($processes as $class) {
                /* @var $process Process */
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
                        asyncCall([$process, 'run']);
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
