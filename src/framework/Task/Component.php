<?php

namespace Framework\Task;

use Amp\Loop;
use Framework\Base\Config;
use Psr\EventDispatcher\EventDispatcherInterface;
use Workerman\Worker;
use function Amp\asyncCall;
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
            //在 TASK_WORKER 进程内有此常量
            define('TASK_WORKER', true);

			$app->startComponents($worker);

			asyncCall(static function() use ($worker, $app) {
                self::connect();
                yield delay(2500);
                Client::watch(Task::class, static function($data) use ($worker, $app) {
                    $callable = wrapCallable($data['callable']);
                    $callableName = is_array($data['callable']) ? join('::', $data['callable']) : $data['callable'];

                    $eventDispatcher = $app->container->get(EventDispatcherInterface::class);
                    $eventDispatcher->dispatch(new TaskCallEvent($worker->id, $callableName));

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