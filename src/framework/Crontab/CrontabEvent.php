<?php

namespace Framework\Crontab;

class CrontabEvent extends \Framework\Event\Event
{

    const TYPE_SCHED = 0;
    const TYPE_EXECUTE = 1;
    const TYPE_RESULT = 2;

    public $name;
    public $type;
    public $interval;
    public $result;

    public function __construct($name, $type, $interval=0, $result=null)
    {
        $this->name = $name;
        $this->type = $type;
        $this->interval = $interval;
        $this->result = $result;
    }

    public function __toString()
    {
        switch ($this->type) {
            case self::TYPE_SCHED: return "{$this->name} will run after {$this->interval} seconds.";
            case self::TYPE_EXECUTE: return "{$this->name} begin execute.";
            case self::TYPE_RESULT:
                if ($this->result instanceof \Throwable) {
                    return "{$this->name} error with: ".get_class($this->result).': '.$this->result->getMessage()."\n".$this->result->getTraceAsString();
                } else {
                    return "{$this->name} execute successfully.";
                }
            default: return '';
        }
    }

}