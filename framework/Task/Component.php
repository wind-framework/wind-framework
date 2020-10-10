<?php

namespace Framework\Task;

use Amp\Loop;
use Workerman\Worker;
use function Amp\call;
use function Amp\delay;
use Framework\Channel\Client;

class Component implements \Framework\Base\Component
{

	/**
	 * 启动 TaskWorker 进程
	 *
	 * @param \Framework\Base\Application $app
	 */
	public static function provide($app) {
		$worker = new Worker();
		$worker->name = 'TaskWorker';
		//Todo: TaskWorker config.
		$worker->count = 2;
		$worker->onWorkerStart = static function($worker) use ($app) {
			$app->startComponents($worker);

			Loop::defer(static function() use ($worker) {
				self::connect();
				Client::watch(Task::class, static function($data) use ($worker) {
					$callableName = is_array($data['callable']) ? join('::', $data['callable']) : $data['callable'];
					Worker::log("TaskWorker {$worker->id} call $callableName().");
					call($data['callable'], ...$data['args'])->onResolve(function($e, $result) use ($data) {
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
		yield delay(1000);
		self::connect();
	}

	private static function connect()
	{
		//Todo: Connect to special channel server
		Client::connect();
	}

}