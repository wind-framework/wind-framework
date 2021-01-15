<?php

namespace Wind\View;

use Wind\Task\Task;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Twig implements ViewInterface
{

    private $twig;

    public function __construct() {
	    $viewDir = BASE_DIR.'/view';
	    $cacheDir = RUNTIME_DIR.'/view';

	    $loader = new FilesystemLoader($viewDir);
	    $this->twig = new Environment($loader, [
		    'cache' => $cacheDir,
		    'auto_reload' => true
	    ]);
    }

	/**
	 * @param string $name
	 * @param array $context
	 * @return string
	 */
    public function render($name, array $context = [])
    {
		return $this->twig->render($name, $context);
	}

	/**
	 * Render view by TaskWorker
	 * 
	 * @param string $name
	 * @param array $context
	 * @return \Amp\Promise<string>
	 */
	public static function renderByTask($name, array $context=[])
	{
		return Task::execute([ViewInterface::class, 'render'], $name, $context);
	}

}