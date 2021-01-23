<?php

namespace Wind\Base;

use Amp\Deferred;

/**
 * 多协程之间数据发送与接收通道
 * @package Wind\Base
 */
class Chan
{

	/**
	 * @var \SplQueue
	 */
	private $queue;

	/**
	 * @var Deferred[]
	 */
	private $consumers = [];

	/**
	 * @var Deferred[]
	 */
	private $getters = [];

	public function __construct() {
		$this->queue = new \SplQueue();
	}

	/**
	 * 往通道中发送数据
	 *
	 * @param mixed $data
	 */
	public function send($data)
	{
		$this->queue->enqueue($data);

		if (count($this->consumers) == 0) {
			return;
		}

		while ($consumer = array_shift($this->consumers)) {
			$data = $this->queue->dequeue();
			$consumer->resolve($data);
			if ($this->queue->isEmpty()) {
				break;
			}
		}
	}

	/**
	 * 从通道中接收数据
	 *
	 * @return \Amp\Promise
	 */
	public function receive()
	{
		$defer = new Deferred();

		if (count($this->getters) > 0) {
			$getter = array_shift($this->getters);
			$getter->resolve($defer);
		} elseif (!$this->queue->isEmpty()) {
			$data = $this->queue->dequeue();
			$defer->resolve($data);
		} else {
			$this->consumers[] = $defer;
		}

		return $defer->promise();
	}

	/**
	 * 获取一个消费者
	 *
	 * 适用于生产者在只有在有消费者消费数据时才写入数据的场景，
	 * 通过 getConsume() 获取到一个消费者后，调用 ->resolve() 向消费者发送数据。
	 *
	 * ```
	 * while (true) {
	 *     $defer = yield $chan->getConsumer();
	 *     $defer->resolve('Hello World');
	 * }
	 * ```
	 *
	 * @return \Amp\Promise<Deferred>
	 */
	public function getConsumer()
	{
		$defer = new Deferred();

		if (count($this->consumers) > 0) {
			$defer->resolve(array_shift($this->consumers));
		} else {
			$this->getters[] = $defer;
		}

		return $defer->promise();
	}

	public function isEmpty()
	{
		return $this->queue->isEmpty();
	}

}