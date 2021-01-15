<?php

namespace Wind\Queue;

class Message
{

    /**
     * 重试次数
     * @var int
     */
    public $attempts = 0;

    /**
     * 队列任务对象
     * @var Job
     */
    public $job;

    /**
     * 消息ID
     * @var string
     */
    public $id;

    /**
     * 消息原始对象
     * @var string|null
     */
    public $raw;

    /**
     * 优先级
     *
     * @var int
     */
    public $priority;

    public function __construct(Job $job, $id=null, $raw=null)
    {
        $this->job = $job;
        $id && $this->id = $id;
        $raw && $this->raw = $raw;
    }

}
