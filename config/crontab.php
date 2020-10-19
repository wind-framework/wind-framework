<?php
/*
Crontab Config

Rule:
*    *    *    *    *
-    -    -    -    -
|    |    |    |    |
|    |    |    |    |
|    |    |    |    +----- day of week (0 - 7) (Sunday=0 or 7)
|    |    |    +---------- month (1 - 12)
|    |    +--------------- day of month (1 - 31)
|    +-------------------- hour (0 - 23)
+------------------------- min (0 - 59)
*/
return [
    'AbcTest' => [
        'enable' => true,
        'rule' => '* * * * *',
        'execute' => [App\Task\TestTask::class, 'abc'],
        'desc' => '执行ABC测试'
    ],
    'AbcTestTwo' => [
        'enable' => true,
        'rule' => '*/2 * * * *',
        'execute' => [App\Task\TestTask::class, 'abc'],
        'desc' => '执行ABC测试每隔2分钟'
    ]
];