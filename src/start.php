<?php

use Framework\Base\Application;
use Workerman\Worker;

require __DIR__.'/vendor/autoload.php';

define('BASE_DIR', __DIR__);
define('RUNTIME_DIR', __DIR__.'/runtime');

Worker::$logFile = RUNTIME_DIR.'/workerman.log';
Worker::$pidFile = RUNTIME_DIR.'/workerman-amphp.pid';

if (!is_dir(RUNTIME_DIR)) {
    mkdir(RUNTIME_DIR, 0775);
}

Application::start();
Worker::runAll();
