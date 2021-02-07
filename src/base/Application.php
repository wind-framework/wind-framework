<?php

namespace Wind\Base;

use DI\ContainerBuilder;
use Wind\Base\Event\SystemError;
use Wind\Web\HttpServer;
use Psr\EventDispatcher\EventDispatcherInterface;
use Workerman\Worker;
use function Amp\asyncCall;

/**
 * 应用程序
 *
 * 应用程序为进程内全局只有一个
 *
 * @package Wind\Base
 * 
 * @property \DI\Container $container
 * @property Config $config
 * @property Worker[] $workers
 * @property int startTimestamp
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
     * The Framework Start Timestamp
     *
     * @var int
     */
    private $startTimestamp;

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

        $this->startTimestamp = time();

        //Todo: 使用 set_exception_handler 会导致程序不能在异常时输出异常和退出，记录系统错误仍需办法
        set_exception_handler(function ($ex) {
            $eventDispatcher = $this->container->get(EventDispatcherInterface::class);
            $eventDispatcher->dispatch(new SystemError($ex));
            echo $ex->__toString();
            exit(250);
        });
    }

    private function runServers()
    {
        $server = $this->config->get('server');

        //Channel Server
        if ($server['channel']['enable']) {
            new \Channel\Server($server['channel']['addr'] ?? '127.0.0.1', $server['channel']['port'] ?? 2206);
        }

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
            case 'container':
            case 'config':
            case 'workers':
            case 'startTimestamp':
                return $this->{$name};
            default: throw new \Error("Try to get undefined property '$name' of Application.");
        }
    }

}