<?php

namespace Framework\Db;

use Amp\Mysql\ConnectionConfig;
use Amp\Promise;
use Amp\Sql\Common\ConnectionPool;
use Framework\Base\Config;
use function Amp\call;
use function Amp\Mysql\pool;

/**
 * Database base Connection and Fetch
 * @package Framework\Db
 */
class Connection
{


	/**
	 * @var \Amp\Mysql\Pool
	 */
	private $pool;

	private $name;

	public function __construct($name) {
		$databases = di()->get(Config::class)->get('database');

		if (!isset($databases[$name])) {
			throw new \Exception("Unable to find database config '{$name}'.");
		}

		$conn = $databases[$name];

		//初始化数据库连接池
		$config = ConnectionConfig::fromString("host={$conn['host']};user={$conn['username']};password={$conn['password']};db={$conn['database']}");

		$maxConnection = $connection['pool']['max_connections'] ?? ConnectionPool::DEFAULT_MAX_CONNECTIONS;
		$maxIdleTime = $connection['pool']['max_idle_time'] ?? ConnectionPool::DEFAULT_IDLE_TIMEOUT;

		$this->pool = pool($config, $maxConnection, $maxIdleTime);
		$this->name = $name;
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @return Promise
	 * @throws \Amp\Sql\ConnectionException
	 * @throws \Amp\Sql\FailureException
	 */
	public function query(string $sql, array $params=[]): Promise
	{
		if ($params) {
			return call(function() use ($sql, $params) {
				$statement = yield $this->pool->prepare($sql);
				return yield $statement->execute($params);
			});
		} else {
			return $this->pool->query($sql);
		}
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @return Promise
	 * @throws \Amp\Sql\ConnectionException
	 * @throws \Amp\Sql\FailureException
	 */
	public function execute(string $sql, array $params = []): Promise
	{
		return $this->pool->execute($sql, $params);
	}

	/**
	 * 查询一条数据出来
	 *
	 * @param string $sql
	 * @param array $params
	 * @return Promise<\Amp\Mysql\ResultSet>
	 */
	public function fetchOne($sql, array $params=[]): Promise {
		return call(function() use ($sql, $params) {
			$result = yield $this->query($sql, $params);

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

	/**
	 * 查询出全部数据
	 *
	 * @param string $sql
	 * @param array $params
	 * @return Promise<\Amp\Mysql\ResultSet>
	 */
	public function fetchAll($sql, array $params=[]): Promise {
		return call(function() use ($sql, $params) {
			$result = yield $this->query($sql, $params);

			$rows = [];

			if (yield $result->advance()) {
				$rows[] = $result->getCurrent();
			}

			return $rows;
		});
	}

}