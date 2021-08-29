<?php

namespace Wind\Task;

use Amp\Deferred;
use Opis\Closure\SerializableClosure;
use Wind\Base\Channel;
use Wind\Utils\StrUtil;

use function Amp\await;

class Task
{

	private static $pid = '';
	private static $eventId = 0;

	/**
	 * Execute and get return in TaskWorker
	 *
	 * @param callable $callable Executor callable, allow coroutine
	 * @param mixed ...$args
	 * @return mixed
	 */
	public static function execute($callable, ...$args)
	{
        $id = self::eventId();
        $defer = new Deferred();
        $returnEvent = Task::class.'@'.$id;

        $channel = di()->get(Channel::class);
        $channel->on($returnEvent, static function($data) use ($defer, $returnEvent, $channel) {
            $channel->unsubscribe($returnEvent);
            list($state, $return) = $data;
            $state ? $defer->resolve($return) : $defer->fail($return);
        });

        if ($callable instanceof \Closure) {
            $callable = new SerializableClosure($callable);
        } elseif (is_array($callable) && is_object($callable[0])) {
            $callable[0] = get_class($callable[0]);
        }

        $channel->enqueue(Task::class, [$id, $callable, $args]);

        return await($defer->promise());
	}

	private static function eventId()
	{
		self::$eventId++;

		if (self::$pid === '') {
			self::$pid = StrUtil::randomString(8);
		}

		return self::$pid.'-'.self::$eventId;
	}

}
