<?php

namespace App\Listener;

use Framework\Collector\CollectorEvent;
use Framework\Crontab\CrontabEvent;
use Framework\Db\QueryEvent;
use Framework\Event\Event;
use Framework\Log\LogFactory;
use Framework\Queue\QueueJobEvent;
use Framework\Task\TaskExecuteEvent;

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
            //TaskExecuteEvent::class,
            CollectorEvent::class,
            CrontabEvent::class,
            QueueJobEvent::class
        ];
    }

    public function handle(Event $event)
    {
        $class = get_class($event);
        $name = substr($class, strrpos($class, '\\')+1);
        $logger = $this->logFactory->get($name);
        $logger->info($event->__toString());
    }
}