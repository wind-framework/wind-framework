<?php

namespace App\Task;

use Wind\Db\Db;

class TestTask
{

    public function abc() {
        echo "TestTask::abc() run~\n";
    }

    public function query() {
        $row = yield Db::fetchOne("SELECT NOW()");
        print_r($row);
    }

}
