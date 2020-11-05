<?php

namespace Framework\Queue;

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

    public function __construct(Job $job, $id=null, $raw=null)
    {
        $this->job = $job;
        $id && $this->id = $id;
        $raw && $this->raw = $raw;
    }

    public function __sleep()
    {
        return ['id', 'job', 'attempts'];
    }

}
