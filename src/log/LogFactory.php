<?php

namespace Wind\Log;

use Monolog\Logger;
use Monolog\Handler\FormattableHandlerTrait;
use RuntimeException;
use Wind\Log\Handler\LogWriterHandler;
use Wind\Log\Handler\TaskWorkerHandler;
use Wind\Utils\ArrayUtil;
use Wind\Utils\PhpUtil;

class LogFactory
{

    /** Task Worker async mode */
    const ASYNC_TASK_WORKER = 0;

    /** Log Writer Process async mode */
    const ASYNC_LOG_WRITER = 1;

    /** @var \Monolog\Handler\HandlerInterface[][] */
    private $groupHandlers = [];

    /**
     * Get Logger
     *
     * @param string $name
     * @param string $group
     * @return \Psr\Log\LoggerInterface
     */
    public function get($name='app', $group='default')
    {
        $handlers = $this->getHandlers($group);
        return new Logger($name, $handlers);
    }

    /**
     * Get handlers for group
     *
     * @param string $group
     * @return \MonoLog\Handler\HandlerInterface[]
     */
    public function getHandlers($group)
    {
        if (isset($this->groupHandlers[$group])) {
            return $this->groupHandlers[$group];
        }

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

        $handlers = [];

        foreach ($setting['handlers'] as $i => $hc) {
            // async logger handler only work in server mode
            $async = WIND_MODE == 'server' ? ($hc['async'] ?? false) : false;
            $args = $hc['args'] ?? [];

            if ($async === false) {
                $handler = di()->make($hc['class'], $args);
            } elseif ($async === self::ASYNC_TASK_WORKER || $async === true) {
                if (defined('TASK_WORKER')) {
                    //已经是 Task Worker 进程中则直接调用该 Handler 同步写入，无需走 TaskWorkerHandler 中转
                    $handler = di()->make($hc['class'], $args);
                } else {
                    $handler = $this->instanceAsyncHandler(TaskWorkerHandler::class, $group, $i, $args);
                }
            } elseif ($async === self::ASYNC_LOG_WRITER) {
                if (defined('LOG_WRITER_PROCESS')) {
                    //LogWriter 进程本身获取的原始 Handler，无需走 LogWriterHandler 中转
                    $handler = di()->make($hc['class'], $args);
                } else {
                    $handler = $this->instanceAsyncHandler(LogWriterHandler::class, $group, $i, $args);
                }
            } else {
                throw new RuntimeException("Unknown async option for log group '$group'.");
            }

            // set formatter for handler that use FormattableHandlerTrait
            $fmt = $hc['formatter'] ?? $setting['formatter'] ?? false;
            if ($fmt && PhpUtil::hasTrait($handler::class, FormattableHandlerTrait::class)) {
                $formatter = di()->make($fmt['class'], $fmt['args'] ?? []);
                $handler->setFormatter($formatter);
            }

            $handlers[$i] = $handler;
        }

        $this->groupHandlers[$group] = $handlers;

        return $handlers;
    }

    public function reset()
    {
        $this->groupHandlers = [];
    }

    /**
     * @return \Wind\Log\Handler\AsyncAbstractHandler
     */
    private function instanceAsyncHandler(string $handlerClass, string $group, int $index, array $args)
    {
        $parameters = ['group'=>$group, 'index'=>$index];

        if ($args) {
            $parameters = $parameters + ArrayUtil::intersectKeys($args, ['level', 'bubble']);
        }

        return di()->make($handlerClass, $parameters);
    }

}
