<?php

namespace App\Collect;

class GcStatusCollect extends \Wind\Collector\Collector
{

    public $runs;
    public $collected;
    public $threshold;
    public $roots;
    public $memoryUsage;
    public $memoryUsageOccupy;
    public $memoryUsagePeak;
    public $error;

    public function collect()
    {
        if (PHP_VERSION_ID >= 70300) {
            $status = gc_status();
            foreach ($status as $k => $v) {
                $this->$k = $v;
            }
            $this->memoryUsage = memory_get_usage();
            $this->memoryUsageOccupy = memory_get_usage(true);
            $this->memoryUsagePeak = memory_get_peak_usage(true);
        } else {
            $this->error = "PHP version ".PHP_VERSION." unsupport gc_status().";
        }
    }

}