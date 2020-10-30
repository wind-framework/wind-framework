<?php
use function DI\create;
return [
	\Framework\View\ViewInterface::class => create(\Framework\View\Twig::class)
];
