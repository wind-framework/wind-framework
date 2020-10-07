<?php

namespace App\Controller;

use App\Collect\GcStatusCollect;
use Framework\Base\Controller;
use Framework\Collector\Collector;
use Framework\Utils\FileUtil;
use Framework\View\Twig;
use function Amp\delay;

class IndexController extends Controller
{

    public function index()
    {
        return 'Hello World';
    }

    public function cache()
    {
        $cache = getApp()->cache;
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
        /* @var $info GcStatusCollect[] */
        $info = yield Collector::get(GcStatusCollect::class);

        usort($info, function($a, $b) {
            return $a->pid <=> $b->pid;
        });

        foreach ($info as &$r) {
            $r->memoryUsage = FileUtil::formatSize($r->memoryUsage);
            $r->memoryUsageOccupy = FileUtil::formatSize($r->memoryUsageOccupy);
            $r->memoryUsagePeak = FileUtil::formatSize($r->memoryUsagePeak);
        }

        return Twig::render('gc-status.twig', ['info'=>$info]);
    }

}