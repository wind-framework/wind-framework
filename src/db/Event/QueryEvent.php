<?php

namespace Wind\Db\Event;

use Wind\Event\Event;

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