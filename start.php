<?php

use App\AmpEvent;
use App\Bootstrap;
use Workerman\Worker;

require __DIR__.'/vendor/autoload.php';

define('BASE_DIR', __DIR__);
Worker::$eventLoopClass = AmpEvent::class;

new Bootstrap();

Worker::$pidFile = __DIR__.'/workerman-amphp.pid';
Worker::runAll();
