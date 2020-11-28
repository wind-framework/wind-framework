<?php

namespace App\Process;

use Framework\Queue\QueueConsumerProcess;

class MyConsumer extends QueueConsumerProcess
{

    protected $queue = 'qredis';

}
