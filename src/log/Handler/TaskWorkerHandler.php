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
        Task::submit([self::class, 'log'], $this->group, $this->index, $record);
    }

    /**
     * Call original handler to process
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
