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

        $row = yield Db::fetchOne("show global status like 'uptime'");
        yield delay(2000);

        print_r($row);
        echo "\r\n";
    }

}
