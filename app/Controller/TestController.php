<?php

namespace App\Controller;

use Framework\Task\Task;

class TestController extends \Framework\Base\Controller
{

	public static function test()
	{
		return 'Hello World '.uniqid();
	}

	public function taskCall()
	{
		return yield Task::execute([TestController::class, 'test']);
	}

}