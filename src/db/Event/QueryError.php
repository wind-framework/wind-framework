<?php


namespace Wind\Db\Event;


class QueryError extends \Wind\Event\Event
{

    public $sql = '';
    public $exception;

    /**
     * QueryError constructor.
     * @param string $sql
     * @param \Exception $exception
     */
    public function __construct($sql, $exception)
    {
        $this->sql = $sql;
        $this->exception = $exception;
    }

    public function __toString()
    {
        return $this->sql."\n".$this->exception->__toString();
    }

}