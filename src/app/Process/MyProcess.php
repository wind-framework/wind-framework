<?php

namespace App\Process;


use Framework\Log\LogFactory;
use function Amp\delay;

class MyProcess extends \Framework\Process\Process
{

    public $name = 'MyProcess';

    public function run()
    {
        $loggerFactory = di()->get(LogFactory::class);
        $logger = $loggerFactory->get($this->name);

        while (1) {
            yield delay(60000);
            $logger->info('Hello this is MyProcess');
        }
    }

}