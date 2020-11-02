<?php

namespace Framework\Queue;

abstract class Job
{

    /**
     * Default TTR
     *
     * @var int
     */
    public $ttr = 60;

    /**
     * Mac attempts to consume job
     *
     * @var int
     */
    public $maxAttempts = 3;

    abstract public function handle();

}
