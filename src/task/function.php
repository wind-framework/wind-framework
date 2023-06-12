<?php

/**
 * Execute the callable in TaskWorker and await the result
 *
 * @param callable $callable
 * @param mixed ...$args
 * @return mixed
 */
function compute($callable, ...$args) {
    return \Wind\Task\Task::await($callable, ...$args);
}
