<?php

namespace Wind\Log;

class Component implements \Wind\Base\Component
{

    public static function provide($app)
    {
        $app->container->set(LogFactory::class, new LogFactory());
    }

    public static function start($worker)
    {
    }

}