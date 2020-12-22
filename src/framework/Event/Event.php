<?php

namespace Framework\Event;

class Event implements \Psr\EventDispatcher\StoppableEventInterface
{

    /**
     * @inheritDoc
     */
    public function isPropagationStopped(): bool
    {
    }

}