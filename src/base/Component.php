<?php

namespace Wind\Base;

use Workerman\Worker;

interface Component
{

    /**
     * 框架初始化时运行
     *
     * 整个框架启动时每个组件只执行一次
     *
     * @param Application $app
     * @return void
     */
    public static function provide($app);

    /**
     * 框架 onWorkerStart 时运行
     *
     * 每个 Worker 启动后执行一次
     *
     * @param Worker $worker
     * @return void
     */
    public static function start($worker);

}