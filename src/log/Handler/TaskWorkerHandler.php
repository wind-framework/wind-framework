<?php

namespace Wind\Log\Handler;

use Monolog\LogRecord;
use Wind\Log\LogFactory;
use Wind\Task\Task;

/**
 * Wind Task Worker Handler
 */
class TaskWorkerHandler extends AsyncAbstractHandler
{

    protected function write(LogRecord $record): void
    {
        //将日志发送至 TaskWorker 处理
        Task::execute([self::class, 'log'], $this->group, $this->index, $record);
    }

    /**
     * 调用原 Handler 处理日志记录
     *
     * @param string $group
     * @param int $index
     * @param LogRecord $record
     * @return bool
     */
    public static function log(string $group, int $index, LogRecord $record)
    {
        $factory = di()->get(LogFactory::class);
        $handler = $factory->getHandlers($group)[$index];
        return $handler->handle($record);
    }

}
