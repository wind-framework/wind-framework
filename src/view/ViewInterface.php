<?php

namespace Wind\View;

interface ViewInterface
{

	/**
	 * @param string $name
	 * @param array $context
	 * @return string
	 */
	public function render($name, array $context = []);

}