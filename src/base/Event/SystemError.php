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
            return fmtException($this->error, config('max_stack_trace'));
        } else {
            return $this->error;
        }
    }

}