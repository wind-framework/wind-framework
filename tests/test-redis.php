<?php

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
            $redis = new Redis('192.168.4.2');
            $redis2 = new Redis('192.168.4.2');

            Timer::add(2, function() use ($redis2) {
                "Add element\n";
                $redis2->lpush('testlist', 'dsafhjsad9fuasd890fu');
            });

            echo "BrPop\n";
            while ($data = yield $redis->brPop('testlist', 0)) {
                print_r($data);
                echo "\n";
            }

            echo "Done pop\n";
        } catch (\Throwable $e) {
            echo dumpException($e);
        }
    });
};

Worker::runAll();