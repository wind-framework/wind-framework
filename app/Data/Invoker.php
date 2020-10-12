<?php

namespace App\Data;

use App\Redis\Cache;
use function Amp\call;

class Invoker
{

	public $cache;

	public function __construct(Cache $cache) {
		$this->cache = $cache;
	}

	public function getCache($input)
	{
		return call(function() use ($input) {
			$lastVisit = yield $this->cache->get('lastvisit');
			return 'Input: '.$input.', Output: '.json_encode($lastVisit);
		});
	}

}