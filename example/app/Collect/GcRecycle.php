<?php

namespace App\Collect;

class GcRecycle extends \Wind\Collector\Collector
{

	public $collected = 0;

	public function collect() {
		$this->collected = gc_collect_cycles();
	}
}