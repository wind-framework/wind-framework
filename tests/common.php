<?php

use Workerman\Worker;
use Wind\Base\Revolt;

require __DIR__.'/../vendor/autoload.php';

Worker::$eventLoopClass = Revolt::class;
Worker::$pidFile = __DIR__.'/../runtime/test.pid';
Worker::$logFile = __DIR__.'/../runtime/test.log';

define('AMP_DEBUG', true);
define('BASE_DIR', __DIR__.'/..');

function dumpException(\Throwable $e) {
    return get_class($e).': ['.$e->getCode().'] '.$e->getMessage()."\n".$e->getTraceAsString()."\n";
}
