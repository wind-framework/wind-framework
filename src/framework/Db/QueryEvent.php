<?php

namespace Framework\Db;

use Framework\Event\Event;

class QueryEvent extends Event
{

    public $sql;
    public $type;

    public function __construct($sql, $type)
    {
        $this->sql = $sql;
        $this->type = $type;
    }

    public function __toString()
    {
        return "{$this->type}: {$this->sql}";
    }

}