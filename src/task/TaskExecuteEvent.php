<?php

namespace Wind\Task;

class TaskExecuteEvent extends \Wind\Event\Event
{

    public $workerId;
    public $callableName;

    public function __construct($workerId, $callableName)
    {
        $this->workerId = $workerId;
        $this->callableName = $callableName;
    }

    public function __toString()
    {
        return "TaskWorker {$this->workerId} call {$this->callableName}().";
    }

}