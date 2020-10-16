<?php

namespace App\Controller;

use App\Collect\GcRecycle;
use App\Collect\GcStatusCollect;
use App\Redis\Cache;
use Framework\Base\Controller;
use Framework\Collector\Collector;
use Framework\Utils\FileUtil;
use Framework\View\ViewInterface;
use Workerman\Protocols\Http\Response;
use function Amp\delay;

class IndexController extends Controller
{

    public function index()
    {
        return 'Hello World'.\env('APP');
    }

    public function cache(Cache $cache)
    {
        //$cache = di()->get(Cache::class);
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

    public function gcStatus(ViewInterface $view)
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

        return $view->render('gc-status.twig', ['info'=>$info]);
    }

    public function gcRecycle()
    {
    	$info = yield Collector::get(GcRecycle::class);
    	return new Response(302, ['Location'=>'/gc-status']);
    }

}