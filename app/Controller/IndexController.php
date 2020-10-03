<?php

namespace App\Controller;

use Amp\Loop;
use App\Controller;
use function Amp\delay;

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

    public function sleep()
    {
        yield delay(5000);
        return 'Sleep';
    }

    public function loopTrigger()
    {
        Loop::run();
    }

}