<?php

namespace Framework\Base;

use Workerman\Worker;

/**
 * 应用程序
 *
 * 应用程序为进程内全局只有一个
 *
 * @package Framework\Base
 */
class Application
{

    private $worker;
    private $components = [];

    /**
     * @var Application
     */
    private static $instance;

    /**
     * @return Application
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    public static function start()
    {
        if (self::$instance !== null) return;

        Worker::$eventLoopClass = Amp::class;

        self::$instance = new Application();
        self::$instance->runServers();
    }

    private function runServers()
    {
        $server = require BASE_DIR.'/config/server.php';

        foreach ($server['servers'] as $srv) {
            switch ($srv['type']) {
                case 'http':
                    $worker = new HttpServer('http://'.$srv['listen']);
                    $worker->count = $srv['worker_num'];
                    $worker->reusePort = false;
                    $this->worker = $worker;
                    break;
            }
        }

        //Add Components
        $components = require BASE_DIR.'/config/component.php';

        foreach ($components as $component) {
            $this->addComponent($component);
        }
    }

    //初始化系统组件
    public function startComponents()
    {
        foreach ($this->components as $component) {
            call_user_func([$component, 'start']);
        }
    }

    public function getWorkerInfo()
    {
        return [
            'id' => $this->worker->id,
            'name' => $this->worker->name,
            'count' => $this->worker->count
        ];
    }

    /**
     * 添加自定义组件
     *
     * @param string $component 自定义组件的入口类名
     * @throws
     */
    public function addComponent($component)
    {
        if (in_array($component, $this->components)) {
            return;
        }

        $ref = new \ReflectionClass($component);

        if (!$ref->isSubclassOf(Component::class)) {
            throw new \Exception("Component $component is not a implement of ".Component::class.".");
        }

        call_user_func([$component, 'provide']);

        $this->components[] = $component;
    }

}