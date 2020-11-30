<?php

use Monolog\Logger;

return [
    'default' => [
        'handlers' => [
            [
                'class' => \Monolog\Handler\RotatingFileHandler::class,
                'args' => [
                    'filename' => RUNTIME_DIR.'/log/app.log',
                    'maxFiles' => 15,
                    'level' => Logger::INFO
                ],
                'async' => true //启用 TaskWorker 模式异步写入
            ],
            [
                'class' => \Framework\Log\StdoutHandler::class,
                'args' => [
                    'level' => Logger::INFO
                ],
                'formatter' => [
                    'class' => \Monolog\Formatter\LineFormatter::class
                ]
            ]
        ]
    ]
];