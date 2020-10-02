<?php

namespace App\Controller;

use App\Controller;

class IndexController extends Controller
{

    public function index()
    {
        return 'Hello World';
    }

    public function cache($context)
    {
        $cache = $context->get('cache');
        $ret = yield $cache->get("lastvisit", "None");
        yield $cache->set("lastvisit", ["last"=>date('Y-m-d H:i:s'), "timestamp"=>time()], 86400);
        return "get: ".print_r($ret, true);
    }

}