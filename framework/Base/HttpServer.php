<?php

namespace Framework\Base;

use FastRoute\Dispatcher;
use Framework\Base\Exception\ExitException;
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
        //初始化路由
        $routes = $this->app->container->get(Config::class)->get('route', []);
        $this->dispatcher = \FastRoute\simpleDispatcher(function(\FastRoute\RouteCollector $c) use ($routes) {
            foreach ($routes as $r) {
                $c->addRoute($r[0], $r[1], $r[2]);
            }
        });

        $this->app->startComponents($worker);
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

                if (is_string($handler) && str_contains($handler, '@')) {
                    list($controller, $action) = explode('@', $handler);
                } else {
                    list($controller, $action) = $handler;
                }

                if (!class_exists($controller)) {
                    goto notfound;
                }

                //实例化控制器类不在协程中，所以不能使用协程
                $controllerInstance = $this->app->container->make($controller);

                //if ($controllerInstance instanceof Controller == false) {
                //    $connection->send(new Response(500, [], "$controller is not a Controller instance."));
                //    return;
                //}

                if (!is_callable([$controllerInstance, $action])) {
                    goto notfound;
                }

                asyncCall(function() use ($controllerInstance, $action, $connection, $request, $vars) {
                    //$context = new Context();
                    //$context->request = $request;
                    //$context->vars = $vars;

                    try {
                        //init() 在此处处理协程的返回状态，所以 init 中可以使用协程，需要在控制器初始化时使用协程请在 init 中使用
                        if (method_exists($controllerInstance, 'init')) {
                            yield wireCall([$controllerInstance, 'init']);
                        }
                        $args = ['request'=>$request] + $vars;
                        $response = yield wireCall([$controllerInstance, $action], $args);
                        $connection->send($response);
                    } catch (ExitException $e) {
                        $connection->send('');
                    } catch (\Throwable $e) {
                        $connection->send(new Response(500, [], $e->getMessage().'<br><pre>'.$e->getTraceAsString().'</pre>'));
                    }
                });
                break;
            case Dispatcher::NOT_FOUND:
                notfound:
                $connection->send(new Response(404, [], "404 Not Found"));
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                //$allowedMethods = $routeInfo[1];
                $connection->send(new Response(405, [],"Method Not Allowed"));
                break;
        }

    }

}