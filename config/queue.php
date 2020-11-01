<?php

return [
    'default' => [
        'driver' => Framework\Queue\Driver\BeanstalkDriver::class,
        'host' => '192.168.4.2',
        'port' => 11300,
        'tube' => 'workman-amp',
        'processes' => 1,
        'concurrent' => 4
    ],
    'qredis' => [
        'driver' => Framework\Queue\Driver\RedisDriver::class,
        'host' => '192.168.4.2',
        'port' => 6379,
        'key' => 'workman-amp',
        'processes' => 1,
        'concurrent' => 4
    ]
];
