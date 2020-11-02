<?php

namespace App\Job;

use Framework\Db\Db;
use Framework\Queue\Job;

use function Amp\delay;

class TestJob extends Job
{

    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function handle()
    {
        echo "Handle job get value: {$this->value}\n";
        yield delay(2000);
        echo "---END---\r\n";
    }

}
