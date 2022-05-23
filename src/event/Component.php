<?php

namespace Wind\Event;

use Psr\EventDispatcher\EventDispatcherInterface;

class Component implements \Wind\Base\Component
{

    public static function provide($app)
    {
        $dispatcher = new EventDispatcher();
        $app->container->set(EventDispatcher::class, $dispatcher);
        $app->container->set(EventDispatcherInterface::class, $dispatcher);

        $listeners = $app->config->get('listener', []);

        foreach ($listeners as $listenerClass) {
            $listener = $app->container->make($listenerClass);
            $dispatcher->addListener($listener);
        }
    }

    public static function start($worker)
    {
    }

}
