<?php

namespace Framework\View;

use Framework\Task\Task;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Twig
{

    private static $twig;

	/**
	 * 渲染模式
	 *
	 * @var string
	 */
    private static $renderMode = 'task';

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

	public static function renderSync($name, array $context = []): string
	{
		return self::twig()->render($name, $context);
	}

	/**
	 * @param $name
	 * @param array $context
	 * @return string|\Amp\Promise<string>
	 */
    public static function render($name, array $context = [])
    {
    	if (self::$renderMode == 'task') {
		    return Task::execute([self::class, 'renderSync'], $name, $context);
	    } else {
		    return self::twig()->render($name, $context);
	    }
    }

}