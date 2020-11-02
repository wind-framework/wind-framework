<?php

return [
    App\Process\MyProcess::class,
    Framework\Crontab\CrontabDispatherProcess::class,
    Framework\Queue\ConsumerProcess::class,
    // App\Process\MyConsumer::class
];
