<?php

namespace App\Helper;

use Psr\SimpleCache\CacheInterface;

class Invoker
{

	public $cache;

	public function __construct(CacheInterface $cache) {
		$this->cache = $cache;
	}

	public function getCache($input)
	{
        $lastVisit = yield $this->cache->get('lastvisit');
        return 'Input: '.$input.', Output: '.json_encode($lastVisit);
	}

	public function someBlock($a)
    {
        $c = '';
        for ($i=0; $i<1000; $i++) {
            $c .= $i;
        }
        sleep(1);
        return $a.$c;
    }

}