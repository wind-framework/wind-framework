<?php

namespace Framework\Task;

use Amp\Deferred;
use Amp\Promise;
use Channel\Client;
use function Amp\call;
use function Amp\delay;

class Task
{

	public static $_runnableWorkers = [];

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
			$returnEvent = Task::class.'@return@'.$id;

			//获取可用的 workerId
			$workerId = null;
			do {
				if (count(self::$_runnableWorkers) > 0) {
					$workerId = array_rand(self::$_runnableWorkers);
				} else {
					print_r(self::$_runnableWorkers);
					yield delay(100);
				}
			} while ($workerId === null);

			Client::publish(Task::class.'@call@'.$workerId, ['id'=>$id, 'callable'=>$callable, 'args'=>$args]);
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