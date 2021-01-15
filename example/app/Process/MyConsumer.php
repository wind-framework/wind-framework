<?php

namespace App\Process;

use Wind\Queue\QueueConsumerProcess;

class MyConsumer extends QueueConsumerProcess
{

    protected $queue = 'qredis';

}
