<?php

namespace Wind\Base\Event;

class SystemError extends \Wind\Event\Event
{

    public $error;

    public function __construct(\Throwable $e)
    {
        $this->error = $e;
    }

    public function __toString()
    {
        return $this->error->__toString();
    }

}