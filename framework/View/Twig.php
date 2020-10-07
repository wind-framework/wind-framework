<?php

namespace Framework\View;

class Twig
{

    private static $twig;

    public function __construct($viewDir, $cacheDir)
    {
        $loader = new \Twig\Loader\FilesystemLoader($viewDir);
        self::$twig = new \Twig\Environment($loader, [
            'cache' => $cacheDir,
            'auto_reload' => true
        ]);
    }

    public static function render($name, array $context = []): string
    {
        return self::$twig->render($name, $context);
    }

}