<?php
// using Loop::defer()

use Workerman\Worker;

require __DIR__.'/vendor/autoload.php';

$worker = new Worker('http://0.0.0.0:2346');
$worker->count = 2;
$worker->onMessage = function($connection, $request) {
    sleep(5);
    $connection->send('Block sleep 5 seconds.');
};

Worker::runAll();