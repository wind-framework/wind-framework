<?php

namespace Framework\Queue;

interface DriverInterface
{

    public function connect();

    public function close();

    public function push(Message $message, $delay=0);

    public function pop();

    public function ack(Message $message);

    public function fail(Message $message);

    public function release(Message $message);

}
