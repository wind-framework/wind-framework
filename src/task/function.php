<?php

/**
 * Execute and get return in TaskWorker
 *
 * @param callable $callable
 * @param mixed ...$args
 * @return mixed
 */
function compute($callable, ...$args) {
    return \Wind\Task\Task::execute($callable, ...$args);
}
