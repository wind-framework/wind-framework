<?php

namespace App\Collect;

class GcRecycle extends \Framework\Collector\Collector
{

	public $collected = 0;

	public function collect() {
		$this->collected = gc_collect_cycles();
	}
}