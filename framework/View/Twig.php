<?php

namespace Framework\View;

use Framework\Task\Task;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Twig
{

    private static $twig;

	/**
	 * @return Environment
	 */
    protected static function twig() {
    	if (self::$twig instanceof Environment) {
    		return self::$twig;
	    }

	    $viewDir = BASE_DIR.'/view';
	    $cacheDir = RUNTIME_DIR.'/view';

	    $loader = new FilesystemLoader($viewDir);
	    return self::$twig = new Environment($loader, [
		    'cache' => $cacheDir,
		    'auto_reload' => true
	    ]);
    }

	/**
	 * @param string $name
	 * @param array $context
	 * @return string
	 */
    public static function render($name, array $context = [])
    {
		return self::twig()->render($name, $context);
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
		return Task::execute([self::class, 'render'], $name, $context);
	}

}