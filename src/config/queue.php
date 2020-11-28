<?php

return [
    'default2' => [
        'driver' => Framework\Queue\Driver\BeanstalkDriver::class,
        'host' => '192.168.4.2',
        'port' => 11300,
        'tube' => 'rim-queue',
        'processes' => 1,
        'concurrent' => 4
    ],
    'default' => [
        'driver' => Framework\Queue\Driver\RedisDriver::class,
        'key' => 'rim-queue',
        'processes' => 1,
        'concurrent' => 4
    ]
];
