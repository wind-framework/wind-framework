<?php

namespace Wind\Task;

use Psr\EventDispatcher\EventDispatcherInterface;
use Wind\Base\Channel;
use Wind\Base\Config;
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
                    list($id, $type, $callable, $args) = $data;

                    if ($type == 'closure') {
                        $callableName = 'Closure';
                        $callable = $callable->getClosure();
                    } else {
                        $callableName = is_array($callable) ? join('::', $callable) : $callable;
                        $callable = wrapCallable($callable);
                    }

                    $eventDispatcher = $app->container->get(EventDispatcherInterface::class);
                    $eventDispatcher->dispatch(new TaskExecuteEvent($worker->id, $callableName));

                    call($callable, ...$args)->onResolve(function($e, $result) use ($id, $channel) {
                        if ($e === null || $e instanceof ExitException) {
                            $channel->publish(Task::class.'@'.$id, [true, $result]);
                        } else {
                            $channel->publish(Task::class.'@'.$id, [false, [
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