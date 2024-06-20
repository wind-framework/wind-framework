<?php

namespace Wind\Socket;

interface AtomicCallback
{

    /**
     * Atomic callback after the command call is successful
     */
    public function callback();

}
