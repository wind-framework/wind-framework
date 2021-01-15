<?php


namespace Wind\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use function Amp\call;
use function Amp\Promise\any;

class EventDispatcher implements EventDispatcherInterface
{

    protected $eventListeners = [];

    /**
     * @param Listener|callable $listener
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
     * @param Listener $listener
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

    public function dispatch(object $event)
    {
        $eventClass = get_class($event);
        if (isset($this->eventListeners[$eventClass])) {
            $ps = [];
            foreach ($this->eventListeners[$eventClass] as $listener) {
                $ps[] = call([$listener, 'handle'], $event);
            }
            return any($ps);
        }
    }

}