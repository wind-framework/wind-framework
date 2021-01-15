<?php

namespace App\Listener;

use Wind\Base\Event\SystemError;
use Wind\Collector\CollectorEvent;
use Wind\Crontab\CrontabEvent;
use Wind\Db\Event\QueryError;
use Wind\Db\Event\QueryEvent;
use Wind\Event\Event;
use Wind\Log\LogFactory;
use Wind\Queue\QueueJobEvent;
use Wind\Task\TaskExecuteEvent;
use Monolog\Logger;

class AppLogListener extends \Wind\Event\Listener
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