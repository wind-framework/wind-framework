<?php

namespace App\Process;


use function Amp\delay;

class MyProcess extends \Framework\Process\Process
{

    public $name = 'MyProcess';

    public function run()
    {
        while (1) {
            yield delay(60000);
            echo "Hello this is MyProcess\n";
        }
    }

}