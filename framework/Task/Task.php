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

		return call(function() use ($callable, $args) {
			$id = uniqid();
			$defer = new Deferred();
			$returnEvent = Task::class.'@'.$id;

			Client::enqueue(Task::class, ['id'=>$id, 'callable'=>$callable, 'args'=>$args]);

			Client::on($returnEvent, function($data) use ($defer, $returnEvent) {
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
				Client::unsubscribe($returnEvent);
			});

			return $defer->promise();
		});
	}

}