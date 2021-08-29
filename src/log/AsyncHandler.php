<?php

namespace Wind\Log;

use Wind\Task\Task;

class AsyncHandler extends \Monolog\Handler\AbstractProcessingHandler
{

    protected $group;

    public function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        if ($this->processors) {
            $record = $this->processRecord($record);
        }

        $this->write($record);

        return false === $this->bubble;
    }

    /**
     * 将日志发送至 TaskWorker 处理
     *
     * @param array $record
     */
    protected function write(array $record): void
    {
        Task::execute(
            [self::class, 'log'],
            $record['channel'],
            $this->group,
            $record['level'],
            $record['message'],
            $record['context']
        );
    }

    /**
     * 供 TaskWorker 调用并使用原配置 Handler 继续处理
     *
     * @param string $name
     * @param string $group
     * @param int $level
     * @param string $message
     * @param array $context
     */
    public static function log($name, $group, $level, $message, $context=[])
    {
        $log = di()->get(LogFactory::class)->get($name, $group);
        //增加异步写标记，异步写标记在 TaskWrapHandler 中作为同步写排除判断
        $context['__WIND_LOG_ASYNC'] = true;
        $log->log($level, $message, $context);
    }

}
