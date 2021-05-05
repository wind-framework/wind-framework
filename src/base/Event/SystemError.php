<?php

namespace Wind\Base\Event;

class SystemError extends \Wind\Event\Event
{

    public $error;

    /**
     * SystemError constructor.
     * @param \Throwable|string $e
     */
    public function __construct($e)
    {
        $this->error = $e;
    }

    public function __toString()
    {
        if ($this->error instanceof \Throwable) {
            return $this->error->__toString();
        } else {
            return $this->error;
        }
    }

}