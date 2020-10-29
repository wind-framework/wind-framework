<?php
use function DI\create;
return [
	\Framework\View\ViewInterface::class => create(\Framework\View\Twig::class),
	\Framework\Queue\QueueInterface::class => create(\Framework\Queue\RedisDriver::class)
];
