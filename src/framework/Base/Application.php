<?php

namespace Framework\Base;

use DI\ContainerBuilder;
use Workerman\Worker;
use function Amp\asyncCall;

/**
 * 应用程序
 *
 * 应用程序为进程内全局只有一个
 *
 * @package Framework\Base
 * 
 * @property \DI\Container $container
 */
class Application
{

    /**
     * @var Worker[]
     */
    private $workers = [];
    private $components = [];

    /**
     * @var \DI\Container
     */
    private $container;

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
        $config = new Config();

        $builder = new ContainerBuilder();
        $builder->addDefinitions($config->get('definitions'));
        $container = $builder->build();
        $container->set(Config::class, $config);

        $this->container = $container;
    }

    private function runServers()
    {
        $config = $this->container->get(Config::class);
        $server = $config->get('server');

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
        $components = $config->get('components');

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
        switch ($name) {
            case 'container':
                return $this->container;
            default:
                throw new \Error("Try to get undefined property '$name' of Application.");
        }
    }

}