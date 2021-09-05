<?php

namespace Wind\Event;

use Wind\Base\Config;
use Psr\EventDispatcher\EventDispatcherInterface;
use function DI\autowire;

class Component implements \Wind\Base\Component
{

    public static function provide($app)
    {
        $app->container->set(EventDispatcherInterface::class, autowire(EventDispatcher::class));
        $dispatcher = $app->container->get(EventDispatcher::class);
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
