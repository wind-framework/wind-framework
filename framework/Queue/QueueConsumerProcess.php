<?php

namespace Framework\Queue;

use Framework\Base\Config;
use Framework\Process\Process;

class QueueConsumerProcess extends Process
{

    public $name = 'QueueConsumer';
    public $queue = 'workamp';

    public function __construct(Config $config)
    {
        
    }

    public function run()
    {

    }

}
