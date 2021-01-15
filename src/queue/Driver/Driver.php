<?php

namespace Wind\Queue\Driver;

use Wind\Queue\Queue;
use Wind\Queue\Message;

abstract class Driver
{

    public abstract function connect();

    /**
     * 放入消息
     *
     * @param Message $message
     * @param int $delay 消息延迟秒数，0代表不延迟
     * @return void
     */
    public abstract function push(Message $message, int $delay);

    public abstract function pop();

    public abstract function ack(Message $message);

    public abstract function fail(Message $message);

    public abstract function release(Message $message, $delay);

    /**
     * 获取消息的已尝试次数
     *
     * @param Message $message
     * @return boolean
     */
    public function attempts(Message $message) {
        return $message->attempts;
    }

}
