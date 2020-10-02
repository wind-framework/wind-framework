<?php

namespace App\Controller;

use App\Controller;
use App\Db;

class DbController extends Controller
{

    public function soul()
    {
        $row = yield Db::fetchOne("SELECT * FROM soul ORDER BY RAND() LIMIT 1");

        if ($row) {
            Db::execute("UPDATE soul SET hits=hits+1 WHERE `id`=?", [$row['id']]);
            return <<<TMPL
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1">
<title>毒鸡汤</title>
<style type="text/css">
html, body {height: 100%; margin:0;}
.container {height:100%; display:grid; place-items:center; padding:0 10px;}
</style>
</head>
<body>
<div class="container">
    <p>来一杯热翔！</p>
    <h1>{$row['title']}</h1>
    <p>干了这碗毒鸡汤！</p>
</div>
</body>
</html>
TMPL;
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