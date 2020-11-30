<?php

namespace Framework\Log;

use Framework\Base\Config;
use Monolog\Logger;

class LogFactory
{

    private $loggers = [];

    public function getLogger($name='app')
    {
        if (isset($this->loggers[$name])) {
            return $this->loggers[$name];
        }

        $config = di()->get(Config::class);

        $setting = $config->get('log.'.$name);

        if (empty($setting)) {
            throw new \Exception("No logger '$name' config found!");
        }

        // create a log channel
        $log = new Logger($name);

        if (!isset($setting['handler'])) {
            throw new \Exception("No handler config for logger '$name'!");
        }

        if (empty($setting['async']) || defined('TASK_WORKER')) {
            //对应 Handler 构造函数的参数
            $ref = new \ReflectionClass($setting['handler']['class']);
            $constructor = $ref->getConstructor();
            $params = $constructor->getParameters();

            $constructArgs = [];

            foreach ($params as $param) {
                if (isset($setting['handler']['args'][$param->name])) {
                    $constructArgs[] = $setting['handler']['args'][$param->name];
                } elseif ($param->isDefaultValueAvailable()) {
                    break;
                } else {
                    throw new \InvalidArgumentException("Can not constructor handler '{$setting['handler']['class']}': No construct value for argument '{$param->name}'.");
                }
            }

            $handler = $ref->newInstanceArgs($constructArgs);
        } else {
            $handler = new AsyncHandler();
        }

        $log->pushHandler($handler);

        return $this->loggers[$name] = $log;
    }

}