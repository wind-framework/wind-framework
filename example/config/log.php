<?php

use Monolog\Formatter\LineFormatter;
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
                'class' => \Wind\Log\StdoutHandler::class,
                'args' => [
                    'level' => Logger::INFO
                ],
                //为 handler 设置独立的 formatter
                'formatter' => [
                    'class' => LineFormatter::class,
                    'args' => [
                        'dateFormat' => 'Y-m-d H:i:s',
                        'allowInlineLineBreaks' => true
                    ]
                ]
            ]
        ],
        //整个组默认的 formatter
        'formatter' => [
            'class' => LineFormatter::class,
            'args' => [
                'dateFormat' => 'Y-m-d H:i:s v',
                'allowInlineLineBreaks' => true
            ]
        ]
    ]
];