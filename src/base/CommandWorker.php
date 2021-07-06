<?php

namespace Wind\Base;

use Amp\Loop;
use Workerman\Worker;

use function Amp\call;
use function Amp\coroutine;
use function Amp\delay;

class CommandWorker extends Worker
{

    /**
     * Get CommandWorker instance
     *
     * @param array $args
     * @return static
     */
    public static function instance($args)
    {
        $worker = new self();
        $worker->onWorkerStart = coroutine(function() use ($worker, $args) {
            yield call([$worker, 'execute'], $args);
            Loop::defer(function() {
                posix_kill(posix_getppid(), SIGINT);
            });
        });
        return $worker;
    }

    public function execute($args)
    {
        yield delay(2000);
        echo "Hello World\n";
    }

}
