<?php

namespace Framework\Db\Event;

use Framework\Event\Event;

class QueryEvent extends Event
{

    public $sql = '';

    public function __construct($sql)
    {
        $this->sql = $sql;
    }

    public function __toString()
    {
        return $this->sql;
    }

}