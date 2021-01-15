<?php

namespace Wind\Db;

use Amp\Promise;

class Db
{

    /**
     * @var Connection[]
     */
    private static $connections = [];

    /**
     * @param string $name
     * @return Connection
     */
    public static function connection($name='default')
    {
    	if (isset(self::$connections[$name])) {
    		return self::$connections[$name];
	    }

    	return self::$connections[$name] = new Connection($name);
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
        return self::connection()->query($sql, $params);
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
        return self::connection()->execute($sql, $params);
    }

    /**
     * 查询一条数据出来
     *
     * @param string $sql
     * @param array $params
     * @return Promise<\Amp\Mysql\ResultSet>
     */
    public static function fetchOne($sql, array $params=[]): Promise {
        return self::connection()->fetchOne($sql, $params);
    }

    /**
     * 查询出全部数据
     *
     * @param string $sql
     * @param array $params
     * @return Promise<\Amp\Mysql\ResultSet>
     */
    public static function fetchAll($sql, array $params=[]): Promise {
        return self::connection()->fetchAll($sql, $params);
    }

    /**
     * Construct a query build from table
     *
     * @param string $name
     * @return QueryBuilder
     */
    public static function table($name)
    {
        return (new QueryBuilder(self::connection()))->from($name);
    }

}
