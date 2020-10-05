<?php

namespace App\Controller;

use Framework\Base\Controller;
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
        if (PHP_VERSION_ID >= 70300) {
            $status = gc_status();
            $info = [
                '垃圾回收运行次数' => $status['runs'],
                '已回收循环引用' => $status['collected'],
                '回收根阈值' => $status['threshold'],
                '当前根数量' => $status['roots']
            ];
            return print_r($info, true);
        } else {
            return "PHP version ".PHP_VERSION." unsupport get gc info.";
        }
    }

}