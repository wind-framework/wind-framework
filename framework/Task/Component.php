<?php

namespace Framework\Task;

use Amp\Loop;
use Framework\Base\Config;
use Workerman\Worker;
use function Amp\call;
use function Amp\delay;
use Framework\Channel\Client;

class Component implements \Framework\Base\Component
{

    private static $enable = true;

	/**
	 * 启动 TaskWorker 进程
	 *
	 * @param \Framework\Base\Application $app
	 */
	public static function provide($app) {
	    $config = $app->container->get(Config::class);
	    $count = $config->get('server.task_worker.worker_num', 0);

	    if ($count == 0) {
            self::$enable = false;
	        return;
        }

		$worker = new Worker();
		$worker->name = 'TaskWorker';
		$worker->count = $count;
		$worker->onWorkerStart = static function($worker) use ($app) {
			$app->startComponents($worker);

			Loop::defer(static function() use ($worker, $app) {
				self::connect();
				Client::watch(Task::class, static function($data) use ($worker, $app) {
					if (is_array($data['callable'])) {
						list($class, $method) = $data['callable'];
						$ref = new \ReflectionClass($class);
						if ($ref->getMethod($method)->isStatic()) {
							$callable = $data['callable'];
						} else {
							//动态类型需要先实例化，这得益于依赖注入才能实现
							//如果该类的构造函数参数不能依赖注入，则不能通过 Task 动态调用
							$object = $app->container->get($class);
							$callable = [$object, $method];
						}
					} else {
						$callable = $data['callable'];
					}

					$callableName = is_array($data['callable']) ? join('::', $data['callable']) : $data['callable'];
					Worker::log("TaskWorker {$worker->id} call $callableName().");

					call($callable, ...$data['args'])->onResolve(function($e, $result) use ($data) {
						if ($e === null) {
							Client::publish(Task::class.'@'.$data['id'], [true, $result]);
						} else {
							Client::publish(Task::class.'@'.$data['id'], [false, [
								'exception' => get_class($e),
								'message' => $e->getMessage(),
								'code' => $e->getCode(),
								'trace' => $e->getTraceAsString()
							]]);
						}
					});
				});
			});
		};
		$app->addWorker($worker);
	}

	/**
	 * @inheritDoc
	 */
	public static function start($worker) {
	    if (self::$enable) {
            yield delay(1000);
            self::connect();
        }
	}

	private static function connect()
	{
        $config = di()->get(Config::class);
        list($host, $port) = explode(':', $config->get('server.task_worker.channel_server', '127.0.0.1:2206'));
		Client::connect($host, $port);
	}

}