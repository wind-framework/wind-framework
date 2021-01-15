<?php

return [
    App\Process\MyProcess::class,
    Wind\Crontab\CrontabDispatherProcess::class,
    Wind\Queue\ConsumerProcess::class,
    // App\Process\MyConsumer::class
];
