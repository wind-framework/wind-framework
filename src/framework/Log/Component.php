<?php

namespace Framework\Log;

class Component implements \Framework\Base\Component
{

    public static function provide($app)
    {
        $app->container->set(LogFactory::class, new LogFactory());
    }

    public static function start($worker)
    {
    }

}