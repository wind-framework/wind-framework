<?php

namespace Wind\Task;

use Amp\DeferredFuture;
use Amp\Future;
use Laravel\SerializableClosure\SerializableClosure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Revolt\EventLoop;
use Wind\Base\Channel;
use Wind\Base\Exception\ExitException;

class Task
{

	private static $pid = '';
	private static $eventId = 0;

	/**
	 * Execute the callable in TaskWorker
	 *
	 * @param callable $callable Executor callable, allow coroutine
	 * @param mixed ...$args
	 * @return \Amp\Future
	 */
	public static function execute($callable, ...$args)
	{
        $defer = new DeferredFuture();

        self::run($callable, $args, static function($state, $return) use ($defer) {
            $state ? $defer->complete($return) : $defer->error($return);
        });

        return $defer->getFuture();
	}

    /**
	 * Execute the callable in TaskWorker and await the result
	 *
	 * @param callable $callable Executor callable
	 * @param mixed ...$args
	 * @return mixed
	 */
    public static function await($callable, ...$args)
    {
        return self::execute($callable, ...$args)->await();
    }

    /**
	 * Execute the callable in TaskWorker but no wait
	 *
	 * @param callable $callable Executor callable
	 * @param mixed ...$args
	 */
    public static function submit($callable, ...$args)
    {
        self::run($callable, $args, static function($state, $return) {
            if (!$state) {
                if (WIND_MODE == 'server') {
                    EventLoop::queue(static fn () => throw $return);
                } else {
                    throw $return;
                }
            }
        });
    }

    private static function run($callable, $args, $callback)
    {
        if (WIND_MODE == 'console') {
            self::runInProcess($callable, $args, $callback);
            return;
        }

		if (self::$pid === '') {
			self::$pid = getmypid();
		}

		$id = self::$pid.'-'.(++self::$eventId);
        $returnEvent = Task::class.'@'.$id;

        $channel = di()->get(Channel::class);

        $channel->on($returnEvent, static function($data) use ($returnEvent, $channel, $callback) {
            $channel->unsubscribe($returnEvent);
            $callback(...$data);
        });

        // serialize callable
        if ($callable instanceof \Closure) {
            $callable = new SerializableClosure($callable);
        } elseif (is_array($callable) && is_object($callable[0])) {
            $callable[0] = get_class($callable[0]);
        }

        $channel->enqueue(Task::class, [$id, $callable, $args]);
    }

    private static function runInProcess($callable, $args, $callback)
    {
        if ($callable instanceof \Closure) {
            $callableName = 'Closure';
        } else {
            $callableName = is_array($callable) ? join('::', $callable) : $callable;
            $callable = wrapCallable($callable);
        }

        $eventDispatcher = di()->get(EventDispatcherInterface::class);
        $eventDispatcher->dispatch(new TaskExecuteEvent(0, $callableName));

        try {
            $result = call_user_func_array($callable, $args);

            if ($result instanceof Future) {
                $result = $result->await();
            }

            $callback(true, $result);

        } catch (ExitException $e) {
            $callback(true, null);
        } catch (\Throwable $e) {
            $callback(false, $e);
        }
    }

}
