<?php

return [
    App\Process\MyProcess::class,
    Framework\Crontab\CrontabDispatherProcess::class,
    Framework\Queue\QueueConsumerProcess::class,
    // App\Process\MyConsumer::class
];
