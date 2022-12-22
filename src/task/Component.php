<?php

namespace Wind\Task;

use Amp\Future;
use Laravel\SerializableClosure\SerializableClosure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Wind\Base\Channel;
use Wind\Base\Config;
use Wind\Base\Exception\ExitException;
use Wind\Log\LogFactory;
use Workerman\Worker;

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

                    if ($result instanceof Future) {
                        $result = $result->await();
                    }

                    $channel->publish(Task::class.'@'.$id, [true, $result]);
                } catch (ExitException $e) {
                    $channel->publish(Task::class.'@'.$id, [true, null]);
                } catch (\Throwable $e) {
                    //Todo: Can not publish Throwable that include Closure.
                    echo $e->__toString();
                    self::flattenExceptionBacktrace($e);
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

    /**
     * Make any PHP Exception serializable by flattening complex values in backtrace.
     *
     * @see https://gist.github.com/Thinkscape/805ba8b91cdce6bcaf7c
     * @param \Exception $exception
     */
    private static function flattenExceptionBacktrace(\Exception $exception) {
        $traceProperty = (new \ReflectionClass('Exception'))->getProperty('trace');
        $traceProperty->setAccessible(true);

        $flatten = function(&$value, $key) {
            if ($value instanceof \Closure) {
                $closureReflection = new \ReflectionFunction($value);
                $value = sprintf(
                    '(Closure at %s:%s)',
                    $closureReflection->getFileName(),
                    $closureReflection->getStartLine()
                );
            } elseif (is_object($value)) {
                $value = sprintf('object(%s)', get_class($value));
            } elseif (is_resource($value)) {
                $value = sprintf('resource(%s)', get_resource_type($value));
            }
        };

        do {
            $trace = $traceProperty->getValue($exception);
            foreach ($trace as &$call) {
                if (!empty($call['args'])) {
                    array_walk_recursive($call['args'], $flatten);
                }
            }
            $traceProperty->setValue($exception, $trace);
        } while($exception = $exception->getPrevious());

        $traceProperty->setAccessible(false);
    }

}
