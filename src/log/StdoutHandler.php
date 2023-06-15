<?php

namespace Wind\Log;

use Monolog\Handler\StreamHandler;
use Monolog\Level;

/**
 * 用于 MonoLog 的控制台输入 Handler
 * @package Wind\Log
 */
class StdoutHandler extends StreamHandler
{

    public function __construct($level = Level::Debug, $bubble = true)
    {
        parent::__construct('php://stdout', $level, $bubble);
    }

}
