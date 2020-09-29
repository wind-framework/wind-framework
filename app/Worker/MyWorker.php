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
        $worker->onMessage = [$this, 'onMessage'];
        $this->worker = $worker;

        $this->cache = new Cache();
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

        echo "start onMessage\n";

        Loop::run(function() use ($connection) {
            $this->controller($connection);
        });

        echo "end onMessage\n";
    }

    public function controller($connection) {
        async(function() use ($connection) {
            $ret = yield $this->cache->get("lastvisit", "None");

            $connection->send("get: ".print_r($ret, true));

            yield $this->cache->set("lastvisit", ["last"=>date('Y-m-d H:i:s'), "timestamp"=>time()], 86400);
        });
    }

}