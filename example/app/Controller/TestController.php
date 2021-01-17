<?php

namespace App\Controller;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request as HttpRequest;
use Amp\Promise;
use App\Helper\Invoker;
use App\Job\TestJob;
use Wind\Base\Config;
use Wind\Log\LogFactory;
use Wind\Queue\Queue;
use Wind\Queue\QueueFactory;
use Wind\Task\Task;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Workerman\Protocols\Http\Request;

class TestController extends \Wind\Web\Controller
{

	public $invoker;

	public function __construct(Invoker $invoker) {
		$this->invoker = $invoker;
	}

    public function taskCall()
	{
		$a = [
		    Task::execute([$this->invoker, 'getCache'], 'ABCDEFG'),
		    compute([$this->invoker, 'someBlock'], 'ABCDEFG')
        ];

		$b = yield Promise\all($a);

		return json_encode($b);
	}

	public function request(Request $req, $id, ContainerInterface $container, CacheInterface $cache)
    {
        $hello = $container->get(Config::class)->get('components')[0];
        return 'Request, id='.$id.', name='.$req->get('name').(yield $cache->get('abc', 'def')).$hello;
	}
	
	public function queue(QueueFactory $factory)
	{
	    $queue = $factory->get('default');
		$ret = [];

		$job = new TestJob('Hello World [Low Priority] '.date('Y-m-d H:i:s'));
		$ret[] = yield $queue->put($job, 2, Queue::PRI_LOW);

		$job = new TestJob('Hello World [Normal Priority] '.date('Y-m-d H:i:s'));
		$ret[] = yield $queue->put($job, 2);

		$job = new TestJob('Hello World [High Priority] '.date('Y-m-d H:i:s'));
		$ret[] = yield $queue->put($job, 2, Queue::PRI_HIGH);

		yield $queue->delete($ret[1]);
		
		return json_encode($ret);
	}

	public function http()
    {
        $client = HttpClientBuilder::buildDefault();
	    $request = new HttpRequest('http://pv.sohu.com/cityjson?ie=utf-8');

        $response = yield $client->request($request);


        $status = $response->getStatus();
        //print_r($response->getHeaders());
        $buffer = yield $response->getBody()->buffer();

        if ($status == 200) {
            $json = substr($buffer, 19, -1);
            $data = json_decode($json, true);
            return "<p>IP：{$data['cip']}</p><p>Location：{$data['cname']}</p>";
        } else {
            return 'Request '.$status.' Error!';
        }
    }

    public function log(LogFactory $logFactory)
    {
        $log = $logFactory->get();

        $e = new \ErrorException('This is a error!');

        // add records to the log
        $log->warning('Foo', [$e]);
        $log->error('Bar');

        yield Task::execute([self::class, 'taskLog']);
        return 'Ok';
    }

    public static function taskLog()
    {
        di()->get(LogFactory::class)->get()->info("Log in task");
    }

}