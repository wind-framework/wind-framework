<?php

namespace App\Listener;

use Framework\Base\Event\SystemError;
use Framework\Collector\CollectorEvent;
use Framework\Crontab\CrontabEvent;
use Framework\Db\Event\QueryError;
use Framework\Db\Event\QueryEvent;
use Framework\Event\Event;
use Framework\Log\LogFactory;
use Framework\Queue\QueueJobEvent;
use Framework\Task\TaskExecuteEvent;
use Monolog\Logger;

class AppLogListener extends \Framework\Event\Listener
{

    private $logFactory;

    public function __construct(LogFactory $logFactory)
    {
        $this->logFactory = $logFactory;
    }

    public function listen(): array
    {
        return [
            QueryEvent::class,
            QueryError::class,
            //TaskExecuteEvent::class,
            //CollectorEvent::class,
            CrontabEvent::class,
            QueueJobEvent::class,
            SystemError::class
        ];
    }

    public function handle(Event $event)
    {
        $class = get_class($event);
        $name = substr($class, strrpos($class, '\\')+1);
        $logger = $this->logFactory->get($name);
        $level = Logger::INFO;

        if ($event instanceof CrontabEvent) {
            if ($event->result instanceof \Throwable) {
                $level = Logger::ERROR;
            }
        } elseif ($event instanceof QueueJobEvent) {
            if ($event->error || $event->state == QueueJobEvent::STATE_ERROR || $event->state == QueueJobEvent::STATE_FAILED) {
                $level = Logger::ERROR;
            }
        } elseif ($event instanceof SystemError || $event instanceof QueryError) {
            $level = Logger::ERROR;
        }

        $logger->log($level, $event->__toString());
    }
}