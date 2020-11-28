<?php

namespace App\Controller;

use Amp\Promise;
use App\Helper\Invoker;
use App\Job\TestJob;
use Framework\Base\Config;
use Framework\Queue\Queue;
use Framework\Task\Task;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Workerman\Protocols\Http\Request;

class TestController extends \Framework\Base\Controller
{

	public $invoker;

	public function __construct(Invoker $invoker) {
		$this->invoker = $invoker;
	}

    public function taskCall()
	{
		$a = [
		    Task::execute([$this->invoker, 'getCache'], 'ABCDEFG'),
		    Task::execute([$this->invoker, 'someBlock'], 'ABCDEFG')
        ];

		$b = yield Promise\all($a);

		return json_encode($b);
	}

	public function request(Request $req, $id, ContainerInterface $container, CacheInterface $cache)
    {
        $hello = $container->get(Config::class)->get('components')[0];
        return 'Request, id='.$id.', name='.$req->get('name').(yield $cache->get('abc', 'def')).$hello;
	}
	
	public function queue()
	{
		$ret = [];

		$job = new TestJob('Hello World [Low Priority] '.date('Y-m-d H:i:s'));
		$ret[] = yield Queue::put('default', $job, 2, Queue::PRI_LOW);

		$job = new TestJob('Hello World [Normal Priority] '.date('Y-m-d H:i:s'));
		$ret[] = yield Queue::put('default', $job, 2, Queue::PRI_NORMAL);

		$job = new TestJob('Hello World [High Priority] '.date('Y-m-d H:i:s'));
		$ret[] = yield Queue::put('default', $job, 2, Queue::PRI_HIGH);
		
		return json_encode($ret);
	}

}