<?php

namespace Framework\Queue;

abstract class Job
{

    protected $maxRetries = 3;
    protected $retryDelay = 5;
    protected $retryCount = 0;

    public function getMaxRetries()
    {
        return $this->maxRetries;
    }

    public function getRetryDelay()
    {
        return $this->retryDelay;
    }

    public function setRetryCount($count)
    {
        $this->retryCount = $count;
    }

    abstract public function handle();

}
