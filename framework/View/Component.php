<?php

namespace Framework\View;

class Component implements \Framework\Base\Component
{

    public static function provide($app)
    {
        $viewDir = BASE_DIR.'/view';
        $cacheDir = RUNTIME_DIR.'/view';

        new Twig($viewDir, $cacheDir);
    }

    public static function start($worker)
    {}

}