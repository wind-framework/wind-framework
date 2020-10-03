<?php

use Framework\Base\Application;
use Workerman\Worker;

require __DIR__.'/vendor/autoload.php';

define('BASE_DIR', __DIR__);

$application = new Application();
$application->addComponent(\Framework\Process\Component::class);

Worker::$pidFile = __DIR__.'/workerman-amphp.pid';
Worker::runAll();
