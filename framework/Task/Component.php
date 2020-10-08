<?php

namespace Framework\Task;

use Amp\Loop;
use Channel\Client;
use Workerman\Worker;
use function Amp\delay;

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
				$controlEvent = Task::class.'@report';
				$worker->busy = false;

				self::connect();
				Client::on(Task::class.'@call@'.$worker->id, static function($data) use ($controlEvent, $worker) {
					//暂停接收任务
					Client::publish($controlEvent, ['pause', $worker->id]);
					$worker->busy = true;

					try {
						$callableName = is_array($data['callable']) ? join('::', $data['callable']) : $data['callable'];
						Worker::log("TaskWorker {$worker->id} call $callableName().");
						$return = call_user_func_array($data['callable'], $data['args']);
						Client::publish(Task::class.'@return@'.$data['id'], [true, $return]);
					} catch (\Throwable $e) {
						Client::publish(Task::class.'@return@'.$data['id'], [false, [
							'exception' => get_class($e),
							'message' => $e->getMessage(),
							'code' => $e->getCode(),
							'trace' => $e->getTraceAsString()
						]]);
					} finally {
						//恢复工作
						Client::publish($controlEvent, ['resume', $worker->id]);
						$worker->busy = false;
					}
				});

				Client::on(Task::class.'@pull', static function() use ($worker, $controlEvent) {
					if (!$worker->busy) {
						Client::publish($controlEvent, ['resume', $worker->id]);
					}
				});

				//告诉客户端自己已经开始工作
				Client::publish($controlEvent, ['start', $worker->id]);
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
		Client::on(Task::class.'@report', function($data) {
			list($command, $id) = $data;
			switch ($command) {
				case 'resume':
				case 'start':
					Task::$_runnableWorkers[$id] = true;
					break;
				case 'pause':
					unset(Task::$_runnableWorkers[$id]);
					break;
			}
		});
		Client::publish(Task::class.'@pull', true);
	}

	private static function connect()
	{
		//Todo: Connect to special channel server
		Client::connect();
	}

}