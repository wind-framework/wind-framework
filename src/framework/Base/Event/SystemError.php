<?php

namespace Framework\Base\Event;

class SystemError extends \Framework\Event\Event
{

    public $error;

    public function __construct(\Throwable $e)
    {
        $this->error = $e;
    }

    public function __toString()
    {
        return get_class($this->error).': '.$this->error->getMessage()."\n".$this->error->getTraceAsString();
    }

}