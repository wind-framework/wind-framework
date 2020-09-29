<?php
use Workerman\Worker;
use App\Worker\MyWorker;

require __DIR__.'/vendor/autoload.php';

new MyWorker();

// 运行worker
Worker::runAll();
