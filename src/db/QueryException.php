<?php

namespace Wind\Db;

use Throwable;

class QueryException extends \Exception
{

    public $sql = '';

    public function __construct($message = "", $code = 0, Throwable $previous = null, $sql='')
    {
        parent::__construct($message, $code, $previous);
        $this->sql = $sql;
    }

}