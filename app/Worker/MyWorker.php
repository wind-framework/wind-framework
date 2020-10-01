<?php

namespace App\Worker;

use Amp\Loop;
use App\Redis\Cache;
use Amp\Mysql;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;
use function Amp\asyncCall;

class MyWorker
{

    private $worker;
    private $cache;

    /** @var \Amp\Mysql\Pool */
    private $dbPool;

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

    		echo "Initialize mysql connection pool..\n";

            $config = Mysql\ConnectionConfig::fromString(
                "host=192.168.4.2;user=root;password=0000;db=test"
            );

            $this->dbPool = Mysql\pool($config);
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

        Loop::run(function() use ($path, $connection) {
            switch ($path) {
                case "/":
                    $this->index($connection);
                    break;
                case "/db":
                    $this->db($connection);
                    break;
            }
        });
    }

    private function index($connection) {
        asyncCall(function() use ($connection) {
            $ret = yield $this->cache->get("lastvisit", "None");

            $connection->send("get: ".print_r($ret, true));

            yield $this->cache->set("lastvisit", ["last"=>date('Y-m-d H:i:s'), "timestamp"=>time()], 86400);
        });
    }

    private function db($connection) {
        asyncCall(function() use ($connection) {
            $result = yield $this->dbPool->query("SELECT * FROM soul ORDER BY RAND() LIMIT 1");

            yield $result->advance();
            $row = $result->getCurrent();

            if (!$row) {
                $connection->send("今天不丧。");
                return;
            }

            $connection->send(print_r($row, true));
            $this->dbPool->execute("UPDATE soul SET hits=hits+1 WHERE `id`=?", [$row['id']]);
        });
    }

}