<?php
/**
 * Created by PhpStorm.
 * User: Pader
 * Date: 2017/5/3
 * Time: 22:28
 */

namespace Wind\Db;

use Amp\Mysql\CommandResult;
use Amp\Promise;
use function Amp\call;

class QueryBuilder {

    protected $connection;
	protected $builder = [];
	protected $table;

	public function __construct(Connection $connection)
	{
	    $this->connection = $connection;
	}

	public function select($fields, $quote=true)
	{
		$this->builder['select'] = $fields;
		$this->builder['select_quote'] = $quote;
		return $this;
	}

    public function from($table, $alias=null)
    {
        $this->table = $this->quoteTable($table);
        return $alias ? $this->alias($alias) : $this;
    }

	public function alias($alias)
	{
		$this->builder['alias'] = $alias;
		return $this;
	}

	public function join($table, $compopr, $type='')
	{
		$type = strtoupper($type);
		$this->builder['join'][] = compact('type', 'table', 'compopr');
		return $this;
	}

	public function leftJoin($table, $compopr) { return $this->join($table, $compopr, 'left'); }
	public function rightJoin($table, $compopr) { return $this->join($table, $compopr, 'right'); }
	public function innerJoin($table, $compopr) { return $this->join($table, $compopr, 'inner'); }
	public function outerJoin($table, $compopr) { return $this->join($table, $compopr, 'outer'); }

	public function union($all=false)
	{
		$sql = $this->buildSelect();
		$this->builder['union'] = $sql.($all ? ' UNION ALL ' : ' UNION ');
		return $this;
	}

	/**
	 * Set where conditions.
	 *
	 * @param array|string $cond Complex where condition array or string.
	 *
	 * Simple condition:
	 * ['id'=>100]
	 * means:
	 * `id`=100
	 *
	 * First element is 'AND', 'OR' mean condition connect method:
	 * ['name'=>'hello', 'nick'=>'world'] -> `name`='hello' AND `nick`='world'
	 * ['OR', 'name'=>'hello', 'nick'=>'world'] -> `name`='hello' OR `nick`='world'
	 *
	 * AND, OR support multiple nested:
	 * ['name'=>'hello', ['OR', 'c'=>1, 'd'=>2]] -> `name`='hello' AND (`c`=1 OR `d`=2)
	 *
	 * IN, NOT IN:
	 * ['name'=>['a', 'b', 'c']] -> `name` IN('a', 'b', 'c') AND
	 * ['name !'=>['a', 'b']] -> `name` NOT IN('a', 'b')
	 *
	 * BETWEEN:
	 * ['id BETWEEN'=>[100, 999]] -> `id` BETWEEN 100 AND 999
	 *
	 * Other symbols:
	 * =, !=, >, >=, <, <=, EXISTS, NOT EXISTS and others
	 * ['id >='=>100, 'live EXISTS'=>'system'] -> `id`>=100 AND `live` EXISTS ('system')
	 *
	 * @param null $params Unsupported yet!
	 * @return $this
	 */
	public function where($cond, $params=null)
	{
		$this->builder['where'] = $cond;

		if ($params !== null) {
			$this->builder['where_params'] = $params;
		}

		return $this;
	}

	public function having($having)
	{
		$this->builder['having'] = $having;
		return $this;
	}

	/**
	 * @param string|array $groupBy
	 * @return $this
	 */
	public function groupBy($groupBy)
	{
		$this->builder['group_by'] = $groupBy;
		return $this;
	}

	/**
	 * @param array|string $order
	 * @return $this
	 */
	public function orderBy($order)
	{
		$this->builder['order_by'] = $order;
		return $this;
	}

	/**
	 * Limit
	 *
	 * @param int $limit
	 * @param int $offset
	 * @return self
	 */
	public function limit($limit, $offset=null)
	{
		$this->builder['limit'] = $limit;
		$offset !== null && $this->builder['offset'] = $offset;
		return $this;
	}

	public function offset($num)
	{
		$this->builder['offset'] = $num;
		return $this;
	}

	/**
	 * Build sql from query builder
	 *
	 * @return string SQL String
	 */
	public function buildSelect()
	{
		//if (!$this->table) {
		//	throw new DatabaseException('Query build error, No table were selected!', $this->di);
		//}

		$sql = '';

		//UNION
		if (isset($this->builder['union'])) {
			$sql .= $this->builder['union'];
		}

		//SELECT
		if (isset($this->builder['select'])) {
			$sql .= 'SELECT '.
				($this->builder['select_quote']
					? $this->quoteKeys($this->builder['select'])
					: $this->builder['select']);
		} else {
			$sql .= 'SELECT *';
		}

		//FROM
		$sql .= $this->buildFrom().$this->buildJoin().$this->buildWhere().$this->buildGroupBy().$this->buildHaving()
			.$this->buildOrderBy().$this->buildLimit().$this->buildOffset();

		return $sql;
	}

	/**
	 * Build from query
	 *
	 * Have alias if setted.
	 *
	 * @see quoteTable()
	 * @return string
	 */
	protected function buildFrom()
	{
		if ($this->table) {
			$sql = " FROM {$this->table}";

			//AS
			if (isset($this->builder['alias'])) {
				$sql .= " `{$this->builder['alias']}`";
			}

			return $sql;
		}

		return '';
	}

	protected function buildJoin()
	{
		if (isset($this->builder['join'])) {
			$sql = '';
			foreach ($this->builder['join'] as $join) {
				$sql .= ' '.$join['type'].' JOIN '.$this->quoteTable($join['table']);
				//only a field mean USING()
				if (is_string($join['compopr']) && preg_match('/^[^\(\)\=]+$/', $join['compopr'])) {
					$sql .= ' USING('.$this->quoteKeys($join['compopr'], true).')';
				} else {
					$sql .= ' ON '.$this->parseJoinCompopr($join['compopr']);
				}
			}
			return $sql;
		}

		return '';
	}

	protected function parseJoinCompopr($compopr)
	{
		if (is_array($compopr)) {
			$cmps = [];

			if (key($compopr) === 0 && ($t = strtoupper($compopr[0])) && ($t == 'AND' || $t == 'OR')) {
				$join = $t;
				unset($compopr[0]);
			} else {
				$join = 'AND';
			}

			foreach ($compopr as $k => $v) {
				$poly = false;
				if (is_int($k)) {
					$str = $this->parseJoinCompopr($v);
					!$poly && count($v) > 1 && $poly = true;
				} else {
					$str = $this->parseWhere($k, $v);
				}
				$cmps[] = $poly ? '('.$str.')' : $str;
			}

			return join(" $join ", $cmps);
		}

		list($lkey, $symbol, $rkey) = preg_split('/\s*([\=\!\<\>]+)\s*/', trim($compopr), 2, PREG_SPLIT_DELIM_CAPTURE);

		return $this->quoteKeys($lkey).$symbol.$this->quoteKeys($rkey);
	}

	protected function buildWhere()
	{
		if (isset($this->builder['where'])) {
			$where = $this->parseWhere($this->builder['where']);
			$sql = " WHERE $where";

			//if (isset($this->builder['where_params']) {
			//}

			return $sql;
		}

		return '';
	}

	protected function buildGroupBy()
	{
		return isset($this->builder['group_by']) ?
			' GROUP BY '.$this->quoteKeys($this->builder['group_by']) : '';
	}

	protected function buildHaving()
	{
		return isset($this->builder['having']) ?
			' HAVING '.$this->parseWhere($this->builder['having']) : '';
	}

	protected function buildOrderBy()
	{
		$sql = '';

		if (isset($this->builder['order_by'])) {
			$orderBy = $this->builder['order_by'];

			//convert order by string to array
			if (!is_array($orderBy)) {
				$orderBy = preg_replace('/\s+/', ' ', $orderBy);
				$arr = array_map('trim', explode(',', $orderBy));
				$orderBy = [];

				foreach ($arr as $ostr) {
					list($field, $sort) = explode(' ', $ostr);
					$orderBy[$field] = $sort;
				}
			}

			$order = '';

			foreach ($orderBy as $field => $sort) {
				$order != '' && $order .= ', ';

				if ($sort === SORT_ASC) {
					$sort = 'ASC';
				} elseif ($sort === SORT_DESC) {
					$sort = 'DESC';
				} else {
					$sort = strtoupper($sort);
				}

				$order .= $this->quoteKeys($field).' '.$sort;
			}

			$sql .= ' ORDER BY '.$order;
		}

		return $sql;
	}

	protected function buildLimit()
	{
		if (isset($this->builder['limit'])) {
			$sql = ' LIMIT ';

			if (isset($this->builder['offset'])) {
				$sql .= "{$this->builder['offset']},";
				unset($this->builder['offset']);
			}

			$sql .= $this->builder['limit'];

			return $sql;
		}

		return '';
	}

	protected function buildOffset()
	{
		return isset($this->builder['offset']) ? " OFFSET {$this->builder['offset']}" : '';
	}

	/**
	 * Convert Keys To SQL Format
	 *
	 * @param string|array $keys
	 * @param bool $single Is a single field in top level
	 * @return string
	 */
	protected function quoteKeys($keys, $single=false)
	{
		if (is_array($keys)) {
			$qk = array();
			foreach($keys as $key) {
				$qk[] = $this->quoteKeys($key, true);
			}
			return join(', ', $qk);
		}

		if (!$single && str_contains($keys, ',')) {
			//split with comma[,] except in brackets[()] and single quots['']
			$xkeys = preg_split('/,(?![^(\']*[\)\'])/', $keys);

			if (!isset($xkeys[1]) || $xkeys[0] == $keys) {
				return $this->quoteKeys($keys, true);
			} else {
				return $this->quoteKeys($xkeys);
			}
		}

		$keys = $col = $this->trim($keys);
		$str = $pre = $func = $alias = $as = '';
		$quote = true;

		//as
		if (($asa = $this->splitAs($keys)) !== false) {
			list($col, $as, $alias) = $asa;
		}

		//used function, two [?] for 1 is not match after space, 2 is not only space in brackets
		if (preg_match('/^(\w+)\s*\(\s*(.+?)?\s*\)$/i', $col, $m)) {
			$func = strtoupper($m[1]);

			if (!isset($m[2])) {
				$col = '';
				$quote = false;
			} elseif (strpbrk($m[2], ',()') !== false) { //nested function
				$col = $this->quoteKeys($m[2]);
				$quote = false;
			} else {
				$col = $m[2];
			}
		}

		//has prefix
		if (str_contains($col, '.')) {
			list($pre, $col) = explode('.', $col);
			$pre = trim($pre, ' `');
		}

		//add prefix
		$pre && $str = "`$pre`.";

		//add quote
		($col != '*' && $quote && !ctype_digit($col)) && $col = "`$col`";

		//add function
		$func ? $str = "$func($str$col)" : $str .= $col;

		//add alias
		$as && $str .= "$as`$alias`";

		return $str;
	}

	protected function quoteTable($table)
	{
		if (is_array($table)) {
			$str = '';
			foreach ($table as $t) {
				$str != '' && $str .= ',';
				$str .= $this->quoteTable($t);
			}
			return $str;
		}

		if (str_contains($table, ',')) {
			return $this->quoteTable(explode(',', $table));
		}

		$table = $this->trim($table);
		$db = $as = $alias = '';

		//as alias
		if (($asa = $this->splitAs($table)) !== false) {
			list($table, $as, $alias) = $asa;
		}

		//remove space char, because may have " db .  table" situation
		$table = str_replace(' ', '', $table);

		if (str_contains($table, '.')) {
			list($db, $table) = explode('.', $table);
		}

		$prefix = $this->connection->prefix();
		if ($prefix && substr($table, 0, strlen($prefix)) != $prefix) {
            $table = $prefix.$table;
        }

		$str = '';

		$db && $str = "`$db`.";
		$str .= "`$table`";
		$as && $str .= "$as`$alias`";

		return $str;
	}

	/**
	 * Quote values to safe sql string
	 *
	 * @param string|array $values
	 * @return string Keys
	 */
	public function quoteValues($values)
	{
		if (is_array($values)) {
			$vals = array();
			foreach($values as $val) {
				$vals[] = $this->quoteValues($val);
			}
			return join(',', $vals);
		} elseif ($values === null) {
            return 'NULL';
        } else {
			return $this->quote($values);
		}
	}

	/**
	 * Parse where condition to sql format
	 *
	 * @param array|string $where
	 * @param mixed $value
	 * @return string
	 */
	public function parseWhere($where, $value=null)
	{
		if (is_array($where)) {
			$sqlWhere = array();

			//first value is AND or OR to set poly method
			if (key($where) === 0 && ($t = strtoupper($where[0])) && ($t == 'AND' || $t == 'OR')) {
				$join = $t;
				unset($where[0]);
			} else {
				$join = 'AND';
			}

			foreach ($where as $key => $val) {
				$poly = false;

				if (is_int($key)) {
					if (is_array($val)) {
						$sql = $this->parseWhere($val);
						count($val) > 1 && $poly = true;
					} else {
						$sql = $val;
					}
				} else {
					$sql = $this->parseWhere($key, $val);
				}

                if ($sql == '') {
                    continue;
                }

				$sqlWhere[] = $poly ? '('.$sql.')' : $sql;
			}

			return join(" $join ", $sqlWhere);
		}

        if ($value === null) {
            return '';
        }

		$where = $this->trim($where);

		if (($pos = strpos($where, ' ')) === false) {
			$key = $where;
			$cond = '';
		} else {
			$key = substr($where, 0, $pos);
			$cond = strtoupper(substr($where, $pos+1));
		}

		$key = $this->quoteKeys($key);

		if ($cond == '' || $cond == '!') {
			if (is_array($value) && count($value) == 1) {
				$value = $value[0];
			}
			$cond = !is_array($value) ? ($cond == '' ? '=' : '!=') : ($cond == '' ? 'IN' : 'NOT IN');
		}

		switch ($cond) {
			case '=':
			case '!=':
			case '>':
			case '>=':
			case '<':
			case '<=':
			case '<>':
				return $key.$cond.$this->quote($value);
				break;

			case 'IN':
			case 'NOT IN':
				return "$key $cond(".$this->quoteValues($value).')';
				break;

			case 'EXISTS':
			case 'NOT EXISTS':
				return $key.' '.$cond.'('.$value.')';
				break;

			case 'BETWEEN':
				is_numeric($value[0]) || $value[0] = $this->quote($value[0]);
				is_numeric($value[1]) || $value[1] = $this->quote($value[1]);
				return "$key BETWEEN {$value[0]} AND {$value[1]}";
				break;

			default:
				if (preg_match('/^\w+$/i', $cond)) {
					$cond = ' '.$cond.' ';
				}
				return $key.$cond.$this->quoteValues($value);
		}
	}

	public function quote($string) {
		return '\''.addslashes($string).'\'';
	}

    /**
     * Clear condition in builder
     */
    public function clear() {
        $this->builder = [];
    }

	/**
	 * Split the table/field alias string
	 *
	 * @param string $str
	 * @return array|bool
	 */
	protected function splitAs($str)
	{
		//as alias
		if (stripos($str,' AS ') !== false) {
			list($col, $alias) = explode(' AS ', str_replace(' as ', ' AS ', $str));
			$alias = trim($alias, ' ');
			$as = ' AS ';
		} elseif (preg_match('/[^\s]\s[\w\-]+$/i', $str)) {
			//sure it's alias name after space
			//like "field alias", not "some )" or any other string after space not a alias
			list($col, $alias) = preg_split('/\s(?=[\w\-]+$)/i', $str);
			$as = ' ';
		} else {
			return false;
		}

		return [$col, $as, $alias];
	}

	protected function trim($str)
	{
		return preg_replace('/\s+/', ' ', trim($str, ' `'));
	}

	/**
	 * Insert data
	 *
	 * @param array $data
	 * @param bool $replace Use REPLACE INTO
	 * @return Promise<int> Last insert id
	 * @throws
	 */
	public function insert(array $data, $replace=false): Promise
	{
		$keys = $this->quoteKeys(array_keys($data));
		$values = $this->quoteValues($data);

		$sql = ($replace ? 'REPLACE' : 'INSERT')." INTO {$this->table}($keys) VALUES($values)";

		return call(function() use ($sql) {
			$result = yield $this->connection->execute($sql);
			return $result->getLastInsertId();
		});
	}

	/**
	 * Delete data
	 *
	 * @return Promise<int> Affected row count
	 */
	public function delete(): Promise
	{
		return call(function() {
			$sql = 'DELETE'.$this->buildFrom().$this->buildWhere().$this->buildOrderBy()
				.$this->buildLimit().$this->buildOffset();
			$this->builder = [];
			$result = yield $this->connection->execute($sql);
			return $result->getAffectedRowCount();
		});
	}

	/**
	 * Update data
	 *
	 * @param array $data
	 * @return Promise<int> Affected row count
	 */
	public function update(array $data): Promise
	{
		$sql = 'UPDATE '.$this->table.' SET ';
		$sets = [];

		foreach($data as $key => $val) {
			if(substr($key, 0, 1) == '^') {
				$key = $this->quoteKeys(substr($key,1));
				$sets[] = "$key=$val";
			} else {
				$sets[] = $this->quoteKeys($key).'='.$this->quote($val);
			}
		}

		$sql .= join(',', $sets).$this->buildWhere().$this->buildOrderBy() .$this->buildLimit()
			.$this->buildOffset();

		$this->builder = [];

		return call(function() use ($sql) {
			$result = yield $this->connection->execute($sql);
			return $result->getAffectedRowCount();
		});
	}

	/**
	 * Fetch first row from query result
	 * @return Promise<array>|Promise<null>
	 */
	public function fetchOne()
	{
		return $this->connection->fetchOne($this->buildSelect());
	}

	/**
	 * Fetch all rows from query result
	 * @return Promise<array>|Promise<null>
	 */
	public function fetchAll()
	{
		return $this->connection->fetchAll($this->buildSelect());
	}

	//Fetch column from all rows
	public function fetchColumn($col=0)
	{
		return $this->connection->fetchColumn($this->buildSelect(), [], $col);
	}

	//public function distinct($field)
	//{
	//	$this->builder['select'] = "distinct($field)";
	//	return $this;
	//}

	/**
	 * Count rows
	 *
	 * @param string $field
	 * @return int|null
	 */
	public function count($field='*')
	{
        //Count is just temporary select, need to resume select
        $builder = $this->popTempBuilder();

        $this->select("COUNT($field)", false);
        $count = $this->scalar();

        $this->resumeTempBuilder($builder);;

        return $count;
	}

	/**
	 * Return first column value in row
	 *
	 * @param int|string $col
	 * @return mixed
	 * @throws
	 */
	public function scalar($col=0)
	{
	    $sql = $this->buildSelect();
		return call(function() use ($sql, $col) {
			$row = yield $this->connection->fetchOne($sql);

			if ($row === null) {
			    return null;
            }

            if (is_int($col)) {
                $row = array_values($row);
            }

            return $row[$col] ?? null;
		});
	}

    private function popTempBuilder()
    {
        $builder = [];
        foreach (['select', 'select_quote', 'limit', 'offset'] as $key) {
            if (isset($this->builder[$key])) {
                $builder[$key] = $this->builder[$key];
                unset($this->builder[$key]);
            }
        }
        return $builder;
    }

    private function resumeTempBuilder($builder)
    {
        foreach (['select', 'select_quote', 'limit', 'offset'] as $key) {
            if (isset($builder[$key])) {
                $this->builder[$key] = $builder[$key];
            } else {
                unset($this->builder[$key]);
            }
        }
    }
}