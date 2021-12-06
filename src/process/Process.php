<?php

namespace Wind\Process;

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
     * 进程执行业务逻辑代码
     * @return void
     */
    public abstract function run();

}
