<?php

namespace Framework\Log;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * 用于 MonoLog 的控制台输入 Handler
 * @package Framework\Log
 */
class StdoutHandler extends StreamHandler
{

    public function __construct($level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct('php://stdout', $level, $bubble);
    }

}