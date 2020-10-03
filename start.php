<?php

use Framework\Base\Bootstrap;
use Framework\Process\Manage as ProcessManage;
use Workerman\Worker;

require __DIR__.'/vendor/autoload.php';

define('BASE_DIR', __DIR__);

new Bootstrap();
new ProcessManage();

Worker::$pidFile = __DIR__.'/workerman-amphp.pid';
Worker::runAll();
