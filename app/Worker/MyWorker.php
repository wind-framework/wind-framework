<?php

namespace App\Worker;

use Amp\Loop;
use App\Redis\Cache;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;
use function Amp\call as async;

class MyWorker
{

    private $worker;
    private $cache;

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
    	//必须使用 Loop::run 启动 Amp 的全局事件轮询器才能使用它的异步和协程
    	Loop::run(function() {
    		echo "Amp Loop running..\n";
	    });
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

        $this->response($connection);
    }

    private function response($connection) {
        async(function() use ($connection) {
            $ret = yield $this->cache->get("lastvisit", "None");

            $connection->send("get: ".print_r($ret, true));

            yield $this->cache->set("lastvisit", ["last"=>date('Y-m-d H:i:s'), "timestamp"=>time()], 86400);
        });
    }

}