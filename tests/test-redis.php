<?php

use Framework\Base\Config;
use Framework\Redis\Redis;
use Workerman\Lib\Timer;
use Workerman\Worker;
use function Amp\asyncCall;

require __DIR__.'/common.php';


$worker = new Worker();
$worker->reusePort = false;
$worker->onWorkerStart = function() {
    asyncCall(function() {
        try {
            $redis = new Redis(new Config);

            // Timer::add(2, function() use ($redis2) {
            //     "Add element\n";
            //     $redis2->lpush('testlist', 'dsafhjsad9fuasd890fu');
            // });

            echo "BrPop\n";
            $data = yield $redis->brPop('testlist', 1);
            var_dump($data);
            while ($data = yield $redis->brPop('testlist', 1)) {
                var_dump($data);
                echo "\n";
            }

            echo "Done pop\n";
        } catch (\Throwable $e) {
            echo dumpException($e);
        }
    });
};

Worker::runAll();