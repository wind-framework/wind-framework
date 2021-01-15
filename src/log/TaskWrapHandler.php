<?php

namespace Wind\Log;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\FormattableHandlerTrait;
use Monolog\Handler\HandlerInterface;

/**
 * TaskWrapHandler
 *
 * 日志写分为多种情况，当一个 Logger 拥有同步和异步两个 Handler 的时候，同步的写入会在对应的 Worker 进程中直接写入。
 * 异步的会发送至 TaskWorker 中写入。此时会有一种情况，由于 TaskWorker 只持有异步的目标 Handler，而不持有同步 Handler
 * （如果同时直接持有同步 Handler 会造成双重写），导致 TaskWorker 本身执行的闭包要写入日志时，同步的 Handler 就会失效，
 * 比如将一个工作发给 TaskWorker 执行，而这个工作要写日志，若此日志 Handler 是同步的情况下，则什么日志都不会写下。
 *
 * 通过在 TaskWorker 下将所有日志 Handler 包装到 TaskWrapHandler 中（包括同步的），然后在 AsyncHandler
 * （处理发送给 TaskWorker 来写的包装 Handler）中对日志打上 __WAF_ASYNC 标记， 就可以在 TaskWorker 中区分是来自 Worker
 * 的异步写入，还是 TaskWorker 内工作的同步写入。
 *
 * 如果是个同步 Handler 但却有异步写入的标记，那明显该消息已经被对应 Worker 的同步 Handler 写入过，则在此 Handler 中忽略。
 *
 * @package Wind\Log
 */
class TaskWrapHandler extends \Monolog\Handler\AbstractHandler
{

    use FormattableHandlerTrait;

    /**
     * @var AbstractProcessingHandler
     */
    protected $handler;
    protected $sync;

    public function setHandler($handler, $sync)
    {
        $this->handler = $handler;
        $this->sync = $sync;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function handle(array $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        //异步调用的情况下，如果此 Logger 是同步 Logger，则代表消息已经被发起进程写入过了，此处不再写入
        if (isset($record['context']['__WAF_ASYNC'])) {
            unset($record['context']['__WAF_ASYNC']);
            if ($this->sync) {
                return false;
            }
        }

        return $this->handler->handle($record);
    }

    /**
     * {@inheritdoc}
     * @suppress PhanTypeMismatchReturn
     */
    public function setFormatter(FormatterInterface $formatter): HandlerInterface
    {
        $this->handler->setFormatter($formatter);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatter(): FormatterInterface
    {
        return $this->handler->getFormatter();
    }

}