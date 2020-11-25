<?php
use function DI\create;
use function DI\autowire;
return [
	\Framework\View\ViewInterface::class => create(\Framework\View\Twig::class),
    \Psr\SimpleCache\CacheInterface::class => autowire(\Framework\Cache\RedisCache::class),
];
