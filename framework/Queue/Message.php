<?php

namespace Framework\Queue;

class Message
{

    /**
     * 重试次数
     *
     * @var int
     */
    public $retryCount = 0;

    /**
     * 队列任务对象
     *
     * @var Job
     */
    public $job;

    private $var = [];

    public function __construct(Job $job)
    {
        $this->job = $job;
    }

    public function set($k, $v)
    {
        $this->var[$k] = $v;
    }

    public function get($k)
    {
        return $this->var[$k] ?? null;
    }

}
