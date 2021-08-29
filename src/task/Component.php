<?php

namespace Wind\Task;

use Opis\Closure\SerializableClosure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Wind\Base\Channel;
use Wind\Base\Config;
use Wind\Base\Exception\ExitException;
use Wind\Log\LogFactory;
use Workerman\Worker;

use function Amp\asyncCallable;

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

            $channel = $app->container->get(Channel::class);

            $channel->watch(Task::class, asyncCallable(static function($data) use ($worker, $app, $channel) {
                list($id, $callable, $args) = $data;

                if ($callable instanceof SerializableClosure) {
                    $callableName = 'Closure';
                    $callable = $callable->getClosure();
                } else {
                    $callableName = is_array($callable) ? join('::', $callable) : $callable;
                    $callable = wrapCallable($callable);
                }

                $eventDispatcher = $app->container->get(EventDispatcherInterface::class);
                $eventDispatcher->dispatch(new TaskExecuteEvent($worker->id, $callableName));

                try {
                    $result = call_user_func_array($callable, $args);
                    $channel->publish(Task::class.'@'.$id, [true, $result]);
                } catch (ExitException $e) {
                    $channel->publish(Task::class.'@'.$id, [true, null]);
                } catch (\Throwable $e) {
                    $channel->publish(Task::class.'@'.$id, [false, $e]);
                }
            }));
		};

		$app->addWorker($worker);
	}

	/**
	 * @inheritDoc
	 */
	public static function start($worker) {
        $container = di();
        //Reset LogFactory, this fix log AsyncHandler has been initialized before worker start.
        if ($container->has(LogFactory::class)) {
            $container->set(LogFactory::class, new LogFactory());
        }
	}

}
