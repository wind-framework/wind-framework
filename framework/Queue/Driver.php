<?php

namespace Framework\Queue;

abstract class Driver
{

    abstract public function pop();

    abstract public function push(Job $job, $delay=0, $pri=0);

    abstract public function ack();

}
