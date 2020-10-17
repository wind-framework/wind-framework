<?php

namespace Framework\Base;

use FastRoute\Dispatcher;
use Framework\Base\Exception\ExitException;
use Invoker\Invoker;
use Invoker\ParameterResolver\AssociativeArrayResolver;
use Invoker\ParameterResolver\Container\TypeHintContainerResolver;
use Invoker\ParameterResolver\DefaultValueResolver;
use Invoker\ParameterResolver\NumericArrayResolver;
use Invoker\ParameterResolver\ResolverChain;
use Invoker\ParameterResolver\TypeHintResolver;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;
use function Amp\asyncCall;

class HttpServer extends Worker
{

    /**
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * @var Application
     */
    private $app;

    /**
     * @var Invoker
     */
    private $invoker;

    public function __construct($socket_name = '', array $context_option = array())
    {
        parent::__construct($socket_name, $context_option);
        $this->onWorkerStart = [$this, 'onWorkerStart'];
        $this->onMessage = [$this, 'onMessage'];
        $this->app = Application::getInstance();
    }

    /**
     * @param Worker $worker
     */
    public function onWorkerStart($worker)
    {
        $this->app->startComponents($worker);

        //初始化路由
	    $route = $this->app->container->get(Config::class)->get('route');
        $this->dispatcher = \FastRoute\simpleDispatcher($route);

        //初始化依赖注入 callable Invoker
        //此 Invoker 主要加入了 TypeHintResolver，可以调用方法是根据类型注入临时的 Request
        //否则直接使用 $this->container->call()
        $parameterResolver = new ResolverChain(array(
            new NumericArrayResolver,
            new AssociativeArrayResolver,
            new DefaultValueResolver,
            new TypeHintResolver,
            new TypeHintContainerResolver($this->app->container)
        ));

        $this->invoker = new Invoker($parameterResolver, $this->app->container);
    }

    /**
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function onMessage($connection, $request)
    {
        $routeInfo = $this->dispatcher->dispatch($request->method(), $request->path());

        switch ($routeInfo[0]) {
            case Dispatcher::FOUND:
                list(, $handler, $vars) = $routeInfo;

                if (is_array($handler)) {
                	list($class, $action) = $handler;
                } elseif (is_string($handler) && str_contains($handler, '::')) {
	                list($class, $action) = explode('::', $handler);
                } elseif (is_callable($handler)) {
	                $callable = $handler;
                } else {
	                $this->sendPageNotFound($connection);
	                return;
                }

                if (isset($class)) {
	                if (!class_exists($class)) {
		                $this->sendPageNotFound($connection);
		                return;
	                }

                	$reflection = new \ReflectionClass($class);
                	$method = $reflection->getMethod($action);

                	if ($method->isStatic()) {
                		$callable = [$class, $action];
	                } else {
		                //实例化控制器类不在协程中，所以不能使用协程
                		$instance = $this->app->container->make($class);
                		$callable = [$instance, $action];
	                }
                }

                asyncCall(function() use ($callable, $connection, $request, $vars) {
                    try {
                        $vars[Request::class] = $request;

                        //init() 在此处处理协程的返回状态，所以 init 中可以使用协程，需要在控制器初始化时使用协程请在 init 中使用
                        if (is_array($callable) && is_object($callable[0]) && method_exists($callable[0], 'init')) {
                            yield wireCall([$callable[0], 'init'], $vars, $this->invoker);
                        }

                        $response = yield wireCall($callable, $vars, $this->invoker);
                        $connection->send($response);
                    } catch (ExitException $e) {
                        $connection->send('');
                    } catch (\Throwable $e) {
                        $connection->send(new Response(500, [], '<h1>'.$e->getMessage().'</h1><pre>'.$e->getTraceAsString().'</pre>'));
                    }
                });
                break;
            case Dispatcher::NOT_FOUND:
                $this->sendPageNotFound($connection);
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                //$allowedMethods = $routeInfo[1];
                $connection->send(new Response(405, [],"Method Not Allowed"));
                break;
        }

    }

	/**
	 * @param TcpConnection $connection
	 */
    public function sendPageNotFound($connection) {
	    $connection->send(new Response(404, [], "<h1>404 Not Found</h1><p>The page you looking for is not found.</p>"));
    }

}