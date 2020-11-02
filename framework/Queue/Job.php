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

    abstract public function handle();

}
