<?php

namespace Wind\Collector;

class CollectorEvent extends \Wind\Event\Event
{

    public $workerId;
    public $workerName;
    public $event;
    public $action;

    public function __construct($workerId, $workerName, $event, $action)
    {
        $this->workerId = $workerId;
        $this->workerName = $workerName;
        $this->event = $event;
        $this->action = $action;
    }

    public function __toString()
    {
        switch ($this->action) {
            case 'request':
                $desc = "send request {$this->event}";
                break;
            case 'collect':
                $desc = "received request {$this->event}";
                break;
            case 'response':
                $desc = "received response {$this->event}";
                break;
            default:
                $desc = "{$this->action} {$this->event}";
        }

        return "Worker {$this->workerName}[{$this->workerId}] $desc";
    }

}