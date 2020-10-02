<?php

namespace App;

use Amp\Loop;
use Amp\Promise;
use App\Redis\Cache;
use FastRoute\Dispatcher;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;
use function Amp\asyncCoroutine;
use function Amp\call;

class BootstrapWorker
{

    private $worker;
    private $cache;

    /**
     * @var Dispatcher
     */
    private $dispatcher;

    public function __construct()
    {
        $worker = new Worker("http://0.0.0.0:2345");
        $worker->count = 1;
        $worker->onWorkerStart = [$this, 'onWorkerStart'];
        $worker->onMessage = [$this, 'onMessage'];
        $this->worker = $worker;

        $this->cache = new Cache();
    }

    public function onWorkerStart() {
        //初始化路由
        $this->dispatcher = \FastRoute\simpleDispatcher(function(\FastRoute\RouteCollector $r) {
            // {id} must be a number (\d+)
            $r->addRoute('GET', '/user/{id:\d+}', 'get_user_handler');
            // The /{title} suffix is optional
            $r->addRoute('GET', '/articles/{id:\d+}[/{title}]', 'get_article_handler');
            $r->addRoute("GET", "/", "App\Controller\IndexController::index");
            $r->addRoute("GET", "/cache", "App\Controller\IndexController::cache");
            $r->addRoute("GET", "/soul", "App\Controller\DbController::soul");
            $r->addRoute("GET", "/soul/{id:\d+}", "App\Controller\DbController::soulFind");
        });

        //初始化数据库
        new Db();
    }

    /**
     * @param TcpConnection $connection
     * @param Request $request
     */
    public function onMessage($connection, $request)
    {
        $path = $request->path();

        if ($path == "/favicon.ico") {
            $connection->send("");
            return;
        }

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

                Loop::run(function() use ($controllerInstance, $action, $context, $connection) {
                    //init() 在此处处理协程的返回状态，所以 init 中可以使用协程，需要在控制器初始化时使用协程请在 init 中使用
                    $initReturn = $controllerInstance->init();

                    if ($initReturn instanceof Promise || $initReturn instanceof \Generator) {
                        yield $initReturn;
                    }

                    $ret = yield call(function() use ($controllerInstance, $action, $context) {
                        return $controllerInstance->{$action}($context);
                    });

                    $connection->send($ret);
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