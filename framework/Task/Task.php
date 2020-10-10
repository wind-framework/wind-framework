<?php

namespace Framework\Task;

use Amp\Deferred;
use Amp\Promise;
use Framework\Channel\Client;
use function Amp\call;

class Task
{

	/**
	 * @param $callable
	 * @param mixed ...$args
	 * @return Promise
	 */
	public static function execute($callable, ...$args)
	{
		if ($callable instanceof \Closure) {
			throw new \Exception('Can not run closure in Task!');
		}

		if (is_array($callable) && !is_string($callable[0])) {
			throw new \Exception('Only static callable availability!');
		}

		return call(static function() use ($callable, $args) {
			$id = uniqid();
			$defer = new Deferred();
			$returnEvent = Task::class.'@'.$id;

			Client::enqueue(Task::class, ['id'=>$id, 'callable'=>$callable, 'args'=>$args]);

			Client::on($returnEvent, static function($data) use ($defer, $returnEvent) {
				Client::unsubscribe($returnEvent);
				list($state, $return) = $data;
				if ($state) {
					$defer->resolve($return);
				} else {
					if (class_exists($return['exception'])) {
						$defer->fail(new $return['exception']($return['message'], $return['code']));
					} else {
						$defer->fail(new \Exception($return['message'], $return['code']));
					}
				}
			});

			return $defer->promise();
		});
	}

}