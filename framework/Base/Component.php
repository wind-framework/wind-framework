<?php

namespace Framework\Base;

interface Component
{

    /**
     *  框架初始化时运行
     *
     * @return void
     */
    public static function provide();

    /**
     * 框架 onWorkerStart 时运行
     * @return void
     */
    public static function start();

}