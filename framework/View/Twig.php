<?php

namespace Framework\View;

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

    public static function render($name, array $context = []): string
    {
        return self::twig()->render($name, $context);
    }

}