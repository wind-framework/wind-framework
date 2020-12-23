<?php

namespace Framework\Log;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\FormattableHandlerTrait;
use Monolog\Handler\HandlerInterface;

class SyncWrapHandler extends \Monolog\Handler\AbstractHandler
{

    use FormattableHandlerTrait;

    protected $group;

    /**
     * @var AbstractProcessingHandler
     */
    protected $handler;

    public function setGroup($group)
    {
        $this->group = $group;
    }

    public function setHandler($handler)
    {
        $this->handler = $handler;
        return $this;
    }

    public function isHandling(array $record): bool
    {
        if (!parent::isHandling($record)) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function handle(array $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        if (isset($record['context']['__WAF_ASYNC'])) {
            unset($record['context']['__WAF_ASYNC']);
            return false;
        }

        $this->handler->handle($record);
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