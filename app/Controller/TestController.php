<?php

namespace App\Controller;

use Amp\Promise;
use App\Helper\Invoker;
use App\Redis\Cache;
use Framework\Base\Config;
use Framework\Task\Task;
use Psr\Container\ContainerInterface;
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

	public function request(Request $req, $id, ContainerInterface $container, Cache $cache)
    {
        $hello = $container->get(Config::class)->get('components')[0];
        return 'Request, id='.$id.', name='.$req->get('name').(yield $cache->get('abc', 'def')).$hello;
    }

}