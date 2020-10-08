<?php

namespace App\Controller;

use Framework\Task\Task;

class TestController extends \Framework\Base\Controller
{

	public static function test($say)
	{
		return 'Hello World '.$say;
	}

	public function taskCall()
	{
		return yield Task::execute([TestController::class, 'test'], 'dsafdsfdsf');
	}

}