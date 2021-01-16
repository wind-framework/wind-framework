<?php

/**
 * Execute and get return in TaskWorker
 *
 * @param callable $callable
 * @param mixed ...$args
 * @return \Amp\Promise
 */
function compute($callable, ...$args) {
    return \Wind\Task\Task::execute($callable, ...$args);
}
