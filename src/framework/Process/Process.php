<?php

namespace Framework\Process;

abstract class Process
{

    /**
     * 进程的标题，留空则为类名
     * @var string
     */
    public $name;

    /**
     * 进程数量，默认为1
     * @var int
     */
    public $count = 1;

    /**
     * 进程执行代码，支持协程
     * @return void|\Generator|\Amp\Promise
     */
    public abstract function run();

}