<?php

namespace Framework\Log;

use Framework\Base\Config;
use Monolog\Logger;

class LogFactory
{

    private $loggers = [];

    public function get($name='app', $group='default')
    {
        $key = $name.':'.$group;

        if (isset($this->loggers[$key])) {
            return $this->loggers[$key];
        }

        $config = di()->get(Config::class);

        $setting = $config->get('log.'.$group);

        if (empty($setting)) {
            throw new \Exception("Logger group '$group' not found in config.");
        }

        // create a log channel
        $log = new Logger($name);

        if (!isset($setting['handler'])) {
            throw new \Exception("No handler config for logger group '$group'!");
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
            $level = $setting['handler']['args']['level'] ?? Logger::DEBUG;
            $bubble = $setting['handler']['args']['bubble'] ?? true;
            $handler = new AsyncHandler($level, $bubble);
            $handler->setGroup($group);
        }

        $log->pushHandler($handler);

        return $this->loggers[$key] = $log;
    }

}