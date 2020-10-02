<?php

namespace App\Worker;

use Amp\Loop;
use App\Context;
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
        $config = Mysql\ConnectionConfig::fromString(
            "host=192.168.4.2;user=root;password=0000;db=test"
        );
        $this->dbPool = Mysql\pool($config);
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

        $context = new Context();

        Loop::run(function() use ($path, $connection, $context) {
            switch ($path) {
                case "/":
                    $this->index($connection, $context);
                    break;
                case "/db":
                    $this->db($connection, $context);
                    break;
            }
        });
    }

    private function index($connection, $context) {
        asyncCall(function() use ($connection) {
            $ret = yield $this->cache->get("lastvisit", "None");

            $connection->send("get: ".print_r($ret, true));

            yield $this->cache->set("lastvisit", ["last"=>date('Y-m-d H:i:s'), "timestamp"=>time()], 86400);
        });
    }

    private function db($connection, $context) {
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