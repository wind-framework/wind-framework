<?php

namespace Wind\Log;

use Monolog\Logger;

class LogFactory
{

    /** @var \Monolog\Handler\HandlerInterface[][] */
    private $handlers = [];

    /**
     * Get Logger
     *
     * @param string $name
     * @param string $group
     * @return \Psr\Log\LoggerInterface
     */
    public function get($name='app', $group='default')
    {
        if (!isset($this->handlers[$group])) {
            $setting = \config('log.'.$group);

            if (empty($setting)) {
                throw new \Exception("Logger group '$group' not found in config.");
            }

            //支持多 Handlers 或单 Handler 配置
            if (!isset($setting['handlers'])) {
                $setting['handlers'] = [];
            }

            if (isset($setting['handler'])) {
                $setting['handlers'][] = $setting['handler'];
            }

            if (empty($setting['handlers'])) {
                throw new \Exception("No handlers config for logger group '$group'!");
            }

            $task = defined('TASK_WORKER');
            $handlers = [];

            foreach ($setting['handlers'] as $h) {
                $sync = empty($h['async']);

                if ($sync || $task) {
                    $args = $h['args'] ?? [];
                    $handler = di()->make($h['class'], $args);
                    //在 Task 中同步模式要放入 TaskWrapHandler
                    //这里的主要作用是区分 Task 本身的业务写同步日志还是异步的调用，如果是异步调用则不写
                    $task && $handler = (new TaskWrapHandler())->setHandler($handler, $sync);
                } else {
                    $level = $h['args']['level'] ?? Logger::DEBUG;
                    $bubble = $h['args']['bubble'] ?? true;
                    $handler = new AsyncHandler($level, $bubble);
                    $handler->setGroup($group);
                }

                $fmt = $h['formatter'] ?? $setting['formatter'] ?? false;
                if ($fmt) {
                    $formatter = di()->make($fmt['class'], $fmt['args'] ?? []);
                    $handler->setFormatter($formatter);
                }

                $handlers[] = $handler;
            }

            $this->handlers[$group] = $handlers;
        }

        return new Logger($name, $this->handlers[$group]);
    }

}
