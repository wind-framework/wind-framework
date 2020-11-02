<?php

namespace Framework\Queue\Driver;

use Framework\Queue\Message;

abstract class Driver
{

    public abstract function connect();

    public abstract function push(Message $message, $delay=0);

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
