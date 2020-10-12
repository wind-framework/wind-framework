<?php

namespace App\Controller;

use App\Data\Invoker;
use Framework\Task\Task;

class TestController extends \Framework\Base\Controller
{

	public $invoker;

	public function __construct(Invoker $invoker) {
		$this->invoker = $invoker;
	}

	public function taskCall()
	{
		return yield Task::execute([$this->invoker, 'getCache'], 'ABCDEFG');
	}

}