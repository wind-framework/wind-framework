<?php

namespace App;

use Amp\Mysql\ConnectionConfig;
use Amp\Promise;
use function Amp\call;
use function Amp\Mysql\pool;

class Db
{

    /**
     * @var \Amp\Mysql\Pool
     */
    protected static $pool;

    public function __construct()
    {
        //初始化数据库连接池
        $config = ConnectionConfig::fromString(
            "host=192.168.4.2;user=root;password=0000;db=test"
        );
        self::$pool = pool($config);
    }

    /**
     * @param string $sql
     * @param array $params
     * @return Promise
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     */
    public static function query(string $sql, array $params=[]): Promise
    {
        if ($params) {
            return call(function() use ($sql, $params) {
                $statement = yield self::$pool->prepare($sql);
                return yield $statement->execute($params);
            });
        } else {
            return self::$pool->query($sql);
        }

    }

    /**
     * @param string $sql
     * @param array $params
     * @return Promise
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     */
    public static function execute(string $sql, array $params = []): Promise
    {
        return self::$pool->execute($sql, $params);
    }

    /**
     * 查询一条数据出来
     *
     * @param string $sql
     * @param array $params
     * @return Promise<\Amp\Mysql\ResultSet>
     */
    public static function fetchOne($sql, array $params=[]): Promise {
        return call(function() use ($sql, $params) {
            $result = yield self::query($sql, $params);

            if (yield $result->advance()) {
                $row = $result->getCurrent();
                //必须持续调用 nextResultSet 或 advance 直到无数据为止
                //防止资源未释放时后面的查询建立新连接的问题
                //如果查询出的数据行数大于一条，则仍然可能出现此问题
                yield $result->nextResultSet();
                return $row;
            } else {
                return null;
            }
        });
    }

}
