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
 * @property Config $config
 * @property Worker[] $workers
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
     * @var Config
     */
    private $config;

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
        self::$instance->initEnv();
        self::$instance->runServers();
        self::$instance->setComponents();
    }

    public function __construct()
    {
        //Config
        $this->config = new Config();

        //Container
        $builder = new ContainerBuilder();
        $builder->addDefinitions($this->config->get('definitions'));
        $container = $builder->build();
        $container->set(Config::class, $this->config);
        $this->container = $container;
    }

    private function initEnv()
    {
        $timezone = $this->config->get('default_timezone');
        if ($timezone) {
            date_default_timezone_set($timezone);
        }
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
    }

    private function setComponents()
    {
        $components = $this->config->get('components');
        foreach ($components as $component) {
            $this->addComponent($component);
        }
    }

    public function addWorker(Worker $worker)
    {
        $this->workers[] = $worker;
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
            case 'container': return $this->container;
            case 'config': return $this->config;
            case 'workers': return $this->workers;
            default: throw new \Error("Try to get undefined property '$name' of Application.");
        }
    }

}