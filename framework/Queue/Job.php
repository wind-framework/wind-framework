<?php

namespace Framework\Queue;

abstract class Job
{

    abstract public function consume();

}
