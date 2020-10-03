<?php

use App\AmpEvent;
use App\Bootstrap;
use Workerman\Worker;

require __DIR__.'/vendor/autoload.php';

Worker::$eventLoopClass = AmpEvent::class;

new Bootstrap();

Worker::$pidFile = __DIR__.'/workerman-amphp.pid';
Worker::runAll();
