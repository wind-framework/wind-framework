<?php

namespace Wind\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use function Amp\call;

class EventDispatcher implements EventDispatcherInterface
{

    protected $eventListeners = [];

    /**
     * @param Listener $listener
     */
    public function addListener(Listener $listener)
    {
        foreach ($listener->listen() as $event) {
            if (!isset($this->eventListeners[$event]) || !in_array($listener, $this->eventListeners[$event], true)) {
                $this->eventListeners[$event][] = $listener;
            }
        }
    }

    /**
     * @param ?Listener $listener
     */
    public function removeListener(Listener $listener=null)
    {
        foreach ($listener->listen() as $event) {
            if (isset($this->eventListeners[$event]) && ($index = array_search($listener, $this->eventListeners[$event], true)) !== false) {
                unset($this->eventListeners[$event][$index]);
                if (!$this->eventListeners[$event]) {
                    unset($this->eventListeners[$event]);
                }
            }
        }
    }

    /**
     * Provide all relevant listeners with an event to process.
     *
     * @param object $event The object to process.
     * @return \Amp\Promise
     */
    public function dispatch(object $event)
    {
        return call(function() use ($event) {
            $eventClass = get_class($event);
            if (isset($this->eventListeners[$eventClass])) {
                foreach ($this->eventListeners[$eventClass] as $listener) {
                    yield call([$listener, 'handle'], $event);
                }
            }
        });
    }

}
