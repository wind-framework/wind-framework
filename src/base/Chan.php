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
	private $receivers = [];

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

		if (count($this->receivers) == 0) {
			return;
		}

		while ($consumer = array_shift($this->receivers)) {
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
			$this->receivers[] = $defer;
		}

		return $defer->promise();
	}

	/**
	 * 获取一个接收者
	 *
	 * 适用于生产者在只有在有接收者消费数据时才写入数据的场景，
	 * 通过 getReceiver() 获取到一个消费者后，调用 ->resolve() 向消费者发送数据。
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
	public function getReceiver()
	{
		$defer = new Deferred();

		if (count($this->receivers) > 0) {
			$defer->resolve(array_shift($this->receivers));
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