<?php

namespace Wind\Base;

use DI\ContainerBuilder;
use DI\Definition\Exception\InvalidDefinition;
use Wind\Base\Event\SystemError;
use Psr\EventDispatcher\EventDispatcherInterface;
use Revolt\EventLoop;
use Wind\Annotation\Collectable;
use Wind\Annotation\Scanner;
use Workerman\Worker;

/**
 * 应用程序
 *
 * 应用程序为进程内全局只有一个
 *
 * @package Wind\Base
 *
 * @property Worker[] $workers
 */
class Application
{

    public readonly \DI\Container $container;

    public readonly Config $config;

    /**
     * The Framework Start Timestamp
     */
    public readonly int $startTimestamp;

    /**
     * @var Application
     */
    private static $instance;

    /**
     * @var Worker[]
     */
    private $workers = [];
    private $components = [];

    /**
     * @return Application
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * Start the wind framework application
     *
     * @param string $mode 'server' or 'console'
     */
    public static function start($mode='server')
    {
        if (PHP_VERSION_ID < 80100) {
            echo "Error: Wind framework require PHP version >= 8.1.0.\n";
            exit(1);
        }

        define('WIND_MODE', $mode);

        if (self::$instance !== null) return;

        self::$instance = new Application();
        self::$instance->initEnv();
        self::$instance->initErrorHandlers();
        self::$instance->initAnnotation();
        self::$instance->setComponents();

        if (WIND_MODE == 'server') {
            self::$instance->runServers();
            Worker::$eventLoopClass = Revolt::class;
            Worker::runAll();
        }
    }

    public function __construct()
    {
        //Config
        $this->config = new Config();

        //Container
        $builder = new ContainerBuilder();
        $builder->useAttributes(false);

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

        $displayError = static function ($ex) use ($dispatchError) {
            $dispatchError($ex);
            echo fmtException($ex, config('max_stack_trace'));
        };

        set_error_handler(static function ($errno, $errstr, $errfile, $errline) use ($friendlyErrorType, $dispatchError) {
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

        set_exception_handler($displayError);
        EventLoop::setErrorHandler($displayError);

        register_shutdown_function(static function () use ($friendlyErrorType, $dispatchError) {
            $error = error_get_last();
            //过滤掉会被 set_error_handler 捕获到错误
            if ($error && !(error_reporting() & $error['type']) && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_STRICT))) {
                $errName = $friendlyErrorType($error['type']);
                $error = "$errName: {$error['message']} in {$error['file']}:{$error['line']}";
                $dispatchError($error);
            }
        });
    }

    private function initAnnotation()
    {
        $map = $this->config->get('annotation.scan_ns_paths');
        if ($map) {
            $scanner = new Scanner();

            foreach ($map as $ns => $path) {
                //filter by wind mode
                if (str_contains($ns, '@')) {
                    [$ns, $mode] = explode('@', $ns);
                    if ($mode != WIND_MODE) {
                        continue;
                    }
                }
                $scanner->addNamespace($ns, $path);
            }

            foreach ($scanner->scan() as $a) {
                $attribute = $a['attribute']->newInstance();
                if ($attribute instanceof Collectable) {
                    if ($a['ref'] instanceof \ReflectionClass) {
                        $attribute->collectClass($a['ref']);
                    } elseif ($a['ref'] instanceof \ReflectionMethod) {
                        $attribute->collectMethod($a['ref']);
                    } else {
                        throw new \RuntimeException('Unknown annotation collect type for '.get_class($attribute));
                    }
                }
            }
        }
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
     */
    public function addComponent($component)
    {
        if (in_array($component, $this->components)) {
            return;
        }

        call_user_func([$component, 'provide'], $this);

        $this->components[] = $component;
    }

    /**
     * 在 Worker 启动时初始化系统组件
     *
     * @param ?Worker $worker
     */
    public function startComponents(?Worker $worker=null)
    {
        foreach ($this->components as $component) {
            if ($worker) {
                defer(static fn() => $component::start($worker));
            } else {
                $component::start($worker);
            }
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
