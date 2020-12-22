<?php

namespace Framework\Event;

use Framework\Base\Config;
use Psr\EventDispatcher\EventDispatcherInterface;
use function DI\autowire;

class Component implements \Framework\Base\Component
{

    public static function provide($app)
    {
        $app->container->set(EventDispatcherInterface::class, autowire(EventDispatcher::class));
        $dispatcher = $app->container->get(EventDispatcherInterface::class);
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