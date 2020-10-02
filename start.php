<?php
use Workerman\Worker;
use App\BootstrapWorker;

require __DIR__.'/vendor/autoload.php';

new BootstrapWorker();

// 运行worker
Worker::runAll();
