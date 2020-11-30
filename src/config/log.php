<?php

use Monolog\Logger;

return [
    'default' => [
        'handler' => [
            'class' => \Monolog\Handler\RotatingFileHandler::class,
            'args' => [
                'filename' => RUNTIME_DIR.'/log/app.log',
                'maxFiles' => 15,
                'level' => Logger::INFO
            ]
        ],
        'async' => true //启用 TaskWorker 模式异步写入
    ]
];