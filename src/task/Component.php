<?php

namespace Wind\Task;

use Opis\Closure\SerializableClosure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Wind\Base\Channel;
use Wind\Base\Config;
use Wind\Base\Exception\ExitException;
use Workerman\Worker;
use function Amp\asyncCall;
use function Amp\call;

class Component implements \Wind\Base\Component
{

	/**
	 * 启动 TaskWorker 进程
	 *
	 * @param \Wind\Base\Application $app
	 */
	public static function provide($app) {
	    $config = $app->container->get(Config::class);
	    $count = $config->get('server.task_worker.worker_num', 0);

	    if ($count == 0) {
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

                    call($callable, ...$args)->onResolve(function($e, $result) use ($id, $channel) {
                        if ($e === null || $e instanceof ExitException) {
                            $channel->publish(Task::class.'@'.$id, [true, $result]);
                        } else {
                            /* @var \Exception $e */
                            //Todo: Can not publish Throwable that include Closure.
                            echo $e->__toString();
                            self::flattenExceptionBacktrace($e);
                            $channel->publish(Task::class.'@'.$id, [false, $e]);
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
            foreach($trace as &$call) {
                array_walk_recursive($call['args'], $flatten);
            }
            $traceProperty->setValue($exception, $trace);
        } while($exception = $exception->getPrevious());

        $traceProperty->setAccessible(false);
    }

}
