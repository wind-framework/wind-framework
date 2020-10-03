<?php
use Workerman\Worker;
use App\BootstrapWorker;

require __DIR__.'/vendor/autoload.php';

Worker::$eventLoopClass = \App\AmpEvent::class;

new BootstrapWorker();

Worker::$pidFile = __DIR__.'/workerman-amphp.pid';
Worker::runAll();
