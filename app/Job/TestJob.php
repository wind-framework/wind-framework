<?php

namespace App\Job;

use Framework\Queue\Job;

class TestJob extends Job
{

    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function handle()
    {
        echo "Handle job get value: {$this->value}";
    }

}
