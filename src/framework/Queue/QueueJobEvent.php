<?php

namespace Framework\Queue;

class QueueJobEvent extends \Framework\Event\Event
{

    const STATE_GET = 0;
    const STATE_SUCCEED = 1;
    const STATE_ERROR = 2;
    const STATE_FAILED = 3;

    public $state;
    public $job;
    public $id;
    public $error;

    public function __construct($state, string $job=null, string $id=null, \Exception $error=null)
    {
        $this->state = $state;
        $this->job = $job;
        $this->id = $id;
        $this->error = $error;
    }

    public function __toString()
    {
        switch ($this->state) {
            case self::STATE_GET: return "Consume Job {$this->job}[{$this->id}] start";
            case self::STATE_SUCCEED: return "Consume Job {$this->job}[{$this->id}] success.";
            case self::STATE_ERROR:
            case self::STATE_FAILED:
                $ex = get_class($this->error);
                $code = $this->error->getCode();
                $msg = $this->error->getMessage();
                return "Consume Job {$this->job}[{$this->id}] ".($this->state == self::STATE_ERROR ? 'error' : 'failed').": $ex: [$code] $msg";
            default:
                return '';
        }
    }

}