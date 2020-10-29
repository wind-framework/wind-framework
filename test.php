<?php

use Workerman\Worker;
use Framework\Base\Amp;

use function Amp\delay;
use function Amp\asyncCall;
use Framework\Queue\BeanstalkClient;

require __DIR__.'/vendor/autoload.php';

Worker::$eventLoopClass = Amp::class;
Worker::$pidFile = __DIR__.'/runtime/test.pid';
Worker::$logFile = __DIR__.'/runtime/test.log';

$worker = new Worker();
$worker->reusePort = false;
$worker->count = 1;
$worker->onWorkerStart = function() {
    asyncCall(function() {
        $client = new BeanstalkClient('192.168.4.2');
        $client->debug = true;
        yield $client->connect();
        echo "Producer connect success.\n";

        for ($i=0; $i<1000; $i++) {
            echo "producer\n";
            $result = yield $client->put("Hello World");
            print_r($result);
        }
    });

    asyncCall(function() {
        $client = new BeanstalkClient('192.168.4.2');
        $client->debug = true;
        yield $client->connect();
        echo "Consumer connect success.\n";

        while ($data = yield $client->reserve()) {
            print_r($data);
            yield $client->delete($data['id']);
            yield delay(100);
        }
    });
};

Worker::runAll();