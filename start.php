<?php

use Framework\Base\Bootstrap;
use Workerman\Worker;

require __DIR__.'/vendor/autoload.php';

define('BASE_DIR', __DIR__);

new Bootstrap();

Worker::$pidFile = __DIR__.'/workerman-amphp.pid';
Worker::runAll();
