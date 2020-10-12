<?php

namespace Framework\Base;

use Workerman\Worker;
use Framework\Base\Config;
use function Amp\asyncCall;

/**
 * 应用程序
 *
 * 应用程序为进程内全局只有一个
 *
 * @package Framework\Base
 * 
 * @property Config $config
 */
class Application
{

    /**
     * @var Worker[]
     */
    private $workers = [];
    private $components = [];

    private $container = [];

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

    public function __construct()
    {
        $this->container['config'] = new Config(BASE_DIR.'/config');
    }

    private function runServers()
    {
        $server = $this->config->get('server');

        foreach ($server['servers'] as $srv) {
        	if (isset($srv['enable']) && $srv['enable'] === false) {
        		break;
	        }

            switch ($srv['type']) {
                case 'http':
                    $worker = new HttpServer('http://'.$srv['listen']);
                    $worker->count = $srv['worker_num'];
                    $worker->reusePort = false;
                    $this->addWorker($worker);
                    break;
	            case 'channel':
	            	list($ip, $port) = explode(':', $srv['listen']);
		            new \Framework\Channel\Server($ip, $port);
	            	break;
            }
        }

        //Add Components
        $components = $this->config->get('components');

        foreach ($components as $component) {
            $this->addComponent($component);
        }
    }

    public function addWorker(Worker $worker)
    {
        $this->workers[] = $worker;
    }

    public function getWorkers()
    {
        return $this->workers;
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

        call_user_func([$component, 'provide'], $this);

        $this->components[] = $component;
    }

    /**
     * 在 Worker 启动时初始化系统组件
     *
     * @param Worker $worker
     */
    public function startComponents(Worker $worker)
    {
        foreach ($this->components as $component) {
        	asyncCall([$component, 'start'], $worker);
        }
    }


    public function __get($name)
    {
        if (isset($this->container[$name])) {
            return $this->container[$name];
        } else {
            throw new \Error("Undefined property '$name' of Application.");
        }
    }

}