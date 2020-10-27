<?php

namespace Framework\Queue;

abstract class Driver
{

    abstract public function pop();

    abstract public function push();

    abstract public function ack();

}
