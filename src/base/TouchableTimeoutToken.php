<?php

namespace Wind\Base;

/**
 * TouchableTimeoutToken can touch multiple TouchableTimeout instance
 */
class TouchableTimeoutToken
{

    /**
     * @var TouchableTimeout[]
     */
    private $timeouts = [];

    private $id = 0;

    /**
     * Subscribe timeout to touch
     */
    public function subscribe(TouchableTimeout $timeout)
    {
        $id = ++$this->id;

        $this->timeouts[$id] = $timeout;

        $timeout->onComplete(function() use ($id) {
            $this->unsubscribe($id);
        });

        return $id;
    }

    public function unsubscribe($id)
    {
        if (isset($this->timeouts[$id])) {
            unset($this->timeouts[$id]);
        }
    }

    /**
     * Touch to recalculate all timeout timers
     */
    public function touch()
    {
        foreach ($this->timeouts as $timeout) {
            $timeout->touch();
        }
    }

}
