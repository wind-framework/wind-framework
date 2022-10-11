<?php

namespace Wind\Event;

use Wind\Base\Config;
use Psr\EventDispatcher\EventDispatcherInterface;

class Component implements \Wind\Base\Component
{

    public static function provide($app)
    {
        $dispatcher = new EventDispatcher();

        $app->container->set(EventDispatcherInterface::class, $dispatcher);
        $app->container->set(EventDispatcher::class, $dispatcher);

        $config = $app->container->get(Config::class);

        $listeners = $config->get('listener', []);

        foreach ($listeners as $listenerClass) {
            $listener = di()->make($listenerClass);
            $dispatcher->addListener($listener);
        }
    }

    public static function start($worker)
    {
    }
}
