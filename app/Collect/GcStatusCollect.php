<?php

namespace App\Collect;

class GcStatusCollect extends \Framework\Collector\Collector
{

    public $runs;
    public $collected;
    public $threshold;
    public $roots;
    public $error;

    public function collect()
    {
        if (PHP_VERSION_ID >= 70300) {
            $status = gc_status();
            foreach ($status as $k => $v) {
                $this->$k = $v;
            }
        } else {
            $this->error = "PHP version ".PHP_VERSION." unsupport gc_status().";
        }
    }

}