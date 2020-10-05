<?php

use Workerman\Worker;

require __DIR__.'/vendor/autoload.php';

Worker::$pidFile = __DIR__.'/runtime/test.pid';
Worker::$logFile = __DIR__.'/runtime/test.log';

$worker = new Worker('http://0.0.0.0:2346');
$worker->count = 10;
$worker->onMessage = function($connection, $request) {
    sleep(5);
    $connection->send('Block sleep 5 seconds.');
};

Worker::runAll();