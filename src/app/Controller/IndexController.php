<?php

namespace App\Controller;

use Amp\Loop;
use App\Collect\GcRecycle;
use App\Collect\GcStatusCollect;
use Framework\Web\Controller;
use Framework\Collector\Collector;
use Framework\Utils\FileUtil;
use Framework\View\ViewInterface;
use Psr\SimpleCache\CacheInterface;
use Workerman\Protocols\Http\Response;
use function Amp\delay;

class IndexController extends Controller
{

    public function index()
    {
        return 'Hello World'.\env('APP');
    }

    public function cache(CacheInterface $cache)
    {
        $ret = yield $cache->get("lastvisit", "None");

        yield $cache->setMultiple(['a'=>111, 'b'=>222, 'c'=>333]);
        $b = yield $cache->getMultiple(['a', 'b', 'c', 'd']);

        return json_encode($b);

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
        //运行时间
        $runSeconds = time() - getApp()->startTimestamp;
        $running = \floor($runSeconds / 86400) . ' 天 '
            . \floor(($runSeconds % 86400) / 3600) . ' 小时 '
            . \floor(($runSeconds % 3600) / 60) . ' 分 '
            . \floor($runSeconds % 60) . ' 秒';

        $driver = Loop::get();
        $event = substr(explode('\\', get_class($driver))[2], 0, -6);

        //内存回收统计
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

        return $view->render('gc-status.twig', compact('info', 'running', 'event'));
    }

    public function gcRecycle()
    {
    	$info = yield Collector::get(GcRecycle::class);
    	return new Response(302, ['Location'=>'/gc-status']);
    }

    public function phpinfo()
    {
        ob_start();
        phpinfo();
        $buf = ob_get_contents();
        ob_end_clean();

        list($php, $configuration) = explode('_______________________________________________________________________', $buf);
        list($configuration, $license) = explode("\nPHP License\n", $configuration);

        list($version, $system, $zend, $stream, $engine) = explode("\n\n", $php);

        return '<pre>'.$version.'<hr>'.$system.'<hr>'.$zend.'<hr>'.$stream.'<hr>'.$engine.'<hr>'.$configuration.'<h2>PHP License</h2>'.$license.'</pre>';
    }

}