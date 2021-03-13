<?php

namespace Wind\Task;

use Wind\Base\Channel;
use Wind\Base\Config;
use Psr\EventDispatcher\EventDispatcherInterface;
use Wind\Base\Exception\ExitException;
use Workerman\Worker;
use function Amp\asyncCall;
use function Amp\call;

class Component implements \Wind\Base\Component
{

    private static $enable = true;

	/**
	 * 启动 TaskWorker 进程
	 *
	 * @param \Wind\Base\Application $app
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
                $channel = $app->container->get(Channel::class);

                $channel->watch(Task::class, static function($data) use ($worker, $app, $channel) {
                    $callable = wrapCallable($data['callable']);
                    $callableName = is_array($data['callable']) ? join('::', $data['callable']) : $data['callable'];

                    $eventDispatcher = $app->container->get(EventDispatcherInterface::class);
                    $eventDispatcher->dispatch(new TaskExecuteEvent($worker->id, $callableName));

                    call($callable, ...$data['args'])->onResolve(function($e, $result) use ($data, $channel) {
                        if ($e === null || $e instanceof ExitException) {
                            $channel->publish(Task::class.'@'.$data['id'], [true, $result]);
                        } else {
                            $channel->publish(Task::class.'@'.$data['id'], [false, [
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
	}


}