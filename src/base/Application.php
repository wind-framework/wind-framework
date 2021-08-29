<?php

namespace Wind\Base;

use DI\ContainerBuilder;
use DI\Definition\Exception\InvalidDefinition;
use Wind\Base\Event\SystemError;
use Psr\EventDispatcher\EventDispatcherInterface;
use Workerman\Worker;

use function Amp\defer;

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

    /**
     * Start the wind framework application
     */
    public static function start()
    {
        if (PHP_VERSION_ID < 80100) {
            echo "Error: Wind framework require PHP version >= 8.1.0.\n";
            exit(1);
        }

        if (self::$instance !== null) return;

        Worker::$eventLoopClass = Amp::class;

        self::$instance = new Application();
        self::$instance->initEnv();
        self::$instance->initErrorHandlers();
        self::$instance->runServers();
        self::$instance->setComponents();

        Worker::runAll();
    }

    public function __construct()
    {
        //Config
        $this->config = new Config();

        //Container
        $builder = new ContainerBuilder();

        if ($this->config->exists('definitions')) {
            $builder->addDefinitions($this->config->get('definitions'));
        }

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
    }

    private function initErrorHandlers()
    {
        //Error handlers
        $friendlyErrorType = function ($type) {
            switch ($type) {
                case E_ERROR:
                    return 'E_ERROR';
                case E_WARNING:
                    return 'E_WARNING';
                case E_PARSE:
                    return 'E_PARSE';
                case E_NOTICE:
                    return 'E_NOTICE';
                case E_CORE_ERROR:
                    return 'E_CORE_ERROR';
                case E_CORE_WARNING:
                    return 'E_CORE_WARNING';
                case E_COMPILE_ERROR:
                    return 'E_COMPILE_ERROR';
                case E_COMPILE_WARNING:
                    return 'E_COMPILE_WARNING';
                case E_USER_ERROR:
                    return 'E_USER_ERROR';
                case E_USER_WARNING:
                    return 'E_USER_WARNING';
                case E_USER_NOTICE:
                    return 'E_USER_NOTICE';
                case E_STRICT:
                    return 'E_STRICT';
                case E_RECOVERABLE_ERROR:
                    return 'E_RECOVERABLE_ERROR';
                case E_DEPRECATED:
                    return 'E_DEPRECATED';
                case E_USER_DEPRECATED:
                    return 'E_USER_DEPRECATED';
            }
            return "";
        };

        $dispatchError = function($error) {
            try {
                $eventDispatcher = $this->container->get(EventDispatcherInterface::class);
                $eventDispatcher->dispatch(new SystemError($error));
            } catch (InvalidDefinition $e) {
            }
        };

        set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($friendlyErrorType, $dispatchError) {
            if (!(error_reporting() & $errno)) {
                // This error code is not included in error_reporting, so let it fall
                // through to the standard PHP error handler
                return false;
            }

            $errName = $friendlyErrorType($errno);
            $error = "$errName: $errstr in $errfile:$errline";
            $dispatchError($error);
            return false;
        }, E_ALL ^ E_NOTICE | E_STRICT);

        set_exception_handler(function ($ex) use ($dispatchError) {
            $dispatchError($ex);
            echo fmtException($ex, config('max_stack_trace'));
        });

        register_shutdown_function(function () use ($friendlyErrorType, $dispatchError) {
            $error = error_get_last();
            //过滤掉会被 set_error_handler 捕获到错误
            if ($error && !(error_reporting() & $error['type']) && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_STRICT))) {
                $errName = $friendlyErrorType($error['type']);
                $error = "$errName: {$error['message']} in {$error['file']}:{$error['line']}";
                $dispatchError($error);
            }
        });
    }

    private function runServers()
    {
        $server = $this->config->get('server');

        //Channel Server
        if ($server['channel']['enable']) {
            new \Channel\Server($server['channel']['addr'] ?? '127.0.0.1', $server['channel']['port'] ?? 2206);
        }

        $supportServers = [
            'http' => \Wind\Web\HttpServer::class,
            'websocket' => \Wind\Web\WebSocketServer::class,
        ];

        foreach ($server['servers'] as $srv) {
        	if (isset($srv['enable']) && $srv['enable'] === false) {
        		break;
	        }

        	if (!isset($supportServers[$srv['type']])) {
                throw new \RuntimeException("Unsupported server type '{$srv['type']}'.");
            }

            $worker = new $supportServers[$srv['type']]($srv['listen'], $srv['context_options'] ?? []);
            $worker->count = $srv['worker_num'] ?? 1;
            $worker->reusePort = $srv['reuse_port'] ?? false;
            $this->addWorker($worker);
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
        	defer([$component, 'start'], $worker);
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
