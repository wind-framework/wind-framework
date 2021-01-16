<?php

use Wind\Base\Application;
use Workerman\Worker;

require __DIR__.'/vendor/autoload.php';

define('BASE_DIR', __DIR__);
define('WORK_DIR', substr(__DIR__, 0, 7) == 'phar://' ? getcwd() : __DIR__);
define('RUNTIME_DIR', WORK_DIR.'/runtime');

Worker::$logFile = RUNTIME_DIR.'/log/workerman.log';
Worker::$pidFile = RUNTIME_DIR.'/workerman-amphp.pid';

if (!is_dir(RUNTIME_DIR)) {
    mkdir(RUNTIME_DIR, 0775);
}

Application::start();
Worker::runAll();
