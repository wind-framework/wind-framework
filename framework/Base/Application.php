<?php

namespace Framework\Base;

use App\Redis\Cache;
use FastRoute\Dispatcher;
use Framework\Db\Db;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;
use function Amp\asyncCall;
use function Amp\call;

class Application
{

    private $worker;
    private $cache;

    /**
     * @var Dispatcher
     */
    private $dispatcher;

    public function __construct()
    {
        Worker::$eventLoopClass = Amp::class;

        $worker = new Worker("http://0.0.0.0:2345");
        $worker->count = 2;

        $worker->onWorkerStart = function() {
            //初始化路由
            echo "Initialize Router..\n";
            $routes = require BASE_DIR . '/config/route.php';
            $this->dispatcher = \FastRoute\simpleDispatcher(function(\FastRoute\RouteCollector $c) use ($routes) {
                foreach ($routes as $r) {
                    $c->addRoute($r[0], $r[1], $r[2]);
                }
            });

            //初始化数据库
            echo "Initialize Database..\n";
            new Db();
        };

        /**
         * @param TcpConnection $connection
         * @param Request $request
         */
        $worker->onMessage = function($connection, $request) {
            $routeInfo = $this->dispatcher->dispatch($request->method(), $request->uri());

            switch ($routeInfo[0]) {
                case Dispatcher::FOUND:
                    list(, $handler, $vars) = $routeInfo;

                    if (is_string($handler) && str_contains($handler, '::')) {
                        list($controller, $action) = explode('::', $handler);
                    } else {
                        list($connection, $action) = $handler;
                    }

                    $context = new Context();
                    $context->set("request", $request);
                    $context->set("cache", $this->cache);
                    $context->set("vars", $vars);

                    if (!class_exists($controller)) {
                        goto notfound;
                    }

                    //实例化控制器类不在协程中，所以不能使用协程
                    $controllerInstance = new $controller;

                    if ($controllerInstance instanceof Controller == false) {
                        $connection->send(new Response(500, [], "$controller is not a Controller instance."));
                        return;
                    }

                    if (!is_callable([$controllerInstance, $action])) {
                        goto notfound;
                    }

                    asyncCall(function() use ($controllerInstance, $action, $context, $connection) {
                        try {
                            //init() 在此处处理协程的返回状态，所以 init 中可以使用协程，需要在控制器初始化时使用协程请在 init 中使用
                            yield call([$controllerInstance, 'init']);
                            $response = yield call([$controllerInstance, $action], $context);
                            $connection->send($response);
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
        } ;

        $this->worker = $worker;

        $this->cache = new Cache();
    }

    /**
     * 添加自定义组件
     *
     * @param string $component 自定义组件的入口类名
     * @throws
     */
    public function addComponent($component)
    {
        $ref = new \ReflectionClass($component);

        if (!$ref->isSubclassOf(Component::class)) {
            throw new \Exception("Component $component is not a implement of ".Component::class.".");
        }

        call_user_func([$component, 'start']);
    }

}