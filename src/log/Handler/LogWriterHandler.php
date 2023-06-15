<?php

namespace Wind\Log\Handler;

use Monolog\LogRecord;
use Wind\Base\Channel;

/**
 * Wind Writer Log Handler
 */
class LogWriterHandler extends AsyncAbstractHandler
{

    const QUEUE_CHANNEL = 'async-log-writer';

    protected function write(LogRecord $record): void
    {
        di()->get(Channel::class)->enqueue(self::QUEUE_CHANNEL, [$this->group, $this->index, $record]);
    }

}
