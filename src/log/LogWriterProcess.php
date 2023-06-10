<?php

namespace Wind\Log;

use Wind\Base\Channel;
use Wind\Log\Handler\LogWriterHandler;
use Wind\Process\Process;

/**
 * Log Writer Process for LogWriterHandler
 */
class LogWriterProcess extends Process
{

    public $name = 'LogWriter';

    public function run() {
        define('LOG_WRITER_PROCESS', true);

        $channel = di()->get(Channel::class);
        $logFactory = di()->get(LogFactory::class);

        $channel->watch(LogWriterHandler::QUEUE_CHANNEL, function($data) use ($logFactory) {
            [$group, $index, $record] = $data;
            $handler = $logFactory->getHandlers($group)[$index];
            $handler->handle($record);
        });
    }

}
