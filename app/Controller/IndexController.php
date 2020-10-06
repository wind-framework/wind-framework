<?php

namespace App\Controller;

use App\Collect\GcStatusCollect;
use Framework\Base\Controller;
use Framework\Collector\Collector;
use Workerman\Protocols\Http\Response;
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
        return 'Sleep 5 seconds.';
    }

    public function block()
    {
        sleep(5);
        return 'Block sleep 5 seconds.';
    }

    public function exception()
    {
        throw new \Exception('Test something wrong!');
    }

    public function gcStatus()
    {
        $info = yield Collector::get(GcStatusCollect::class);
        return new Response(200, ['Content-Type'=>'application/json'], json_encode($info));
    }

}