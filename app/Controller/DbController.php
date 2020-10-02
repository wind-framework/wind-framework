<?php

namespace App\Controller;

use App\Controller;
use App\Db;

class DbController extends Controller
{

    public function soul()
    {
        $result = yield Db::query("SELECT * FROM soul ORDER BY RAND() LIMIT 1");

        if (yield $result->advance()) {
            $row = $result->getCurrent();
            Db::execute("UPDATE soul SET hits=hits+1 WHERE `id`=?", [$row['id']]);
            return print_r($row, true);
        } else {
            return "今天不丧。";
        }
    }

    public function soulFind($context)
    {
        $id = $context->get('vars')['id'];

        $result = yield Db::query("SELECT * FROM soul WHERE `id`=?", [$id]);

        if (yield $result->advance()) {
            $row = $result->getCurrent();
            return print_r($row, true);
        } else {
            return "无该丧。";
        }
    }

}