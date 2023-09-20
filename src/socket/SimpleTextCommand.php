<?php

namespace Wind\Socket;

use Amp\Future;

interface SimpleTextCommand
{

    /**
     * Get command executing deferred future
     */
    public function getFuture(): Future;

    /**
     * Encode this command to send
     */
    public function encode(): string;

    /**
     * Decode buffer from server, and resolve deferred future
     *
     * @param string|\Throwable $buffer Buffer received from server
     */
    public function resolve(string|\Throwable $buffer);

}
