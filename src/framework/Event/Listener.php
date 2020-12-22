<?php

namespace Framework\Event;

abstract class Listener implements \Psr\EventDispatcher\ListenerProviderInterface
{

    public abstract function listen(): array;

    public abstract function handle(Event $event);

    /**
     * @inheritDoc
     */
    public function getListenersForEvent(object $event): iterable
    {
        return $this->listen();
    }

}