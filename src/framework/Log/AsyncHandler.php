<?php

namespace Framework\Log;

use Framework\Task\Task;

class AsyncHandler extends \Monolog\Handler\AbstractProcessingHandler
{

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
            [self::class, 'recordLog'],
            $record['channel'],
            $record['level'],
            $record['message'],
            $record['context']
        )->onResolve(static function($e, $value) {
            if ($e) {
                throw $e;
            }
        });
    }

    /**
     * 供 TaskWorker 调用并使用原配置 Handler 继续处理
     *
     * @param string $channel
     * @param int $level
     * @param string $message
     * @param array $context
     */
    public static function recordLog($channel, $level, $message, $context)
    {
        $factory = di()->get(LogFactory::class);
        $log = $factory->getLogger($channel);
        $log->log($level, $message, $context);
    }

}