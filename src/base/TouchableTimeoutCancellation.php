<?php

namespace Wind\Base;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Internal\Cancellable;
use Amp\TimeoutException;
use Revolt\EventLoop;

/**
 * A TouchableTimeoutCancellation can reset a timeout timer
 *
 * **Example**
 * ```php
 * $cancellation = new TouchableTimeoutCancellation(5);
 *
 * async(function() use ($cancellation) {
 *     delay(4);
 *     $cancellation->touch();
 *     delay(4);
 *     $cancellation->touch(); //Can be touch again
 *     delay(5);
 * }, $cancellation);
 * ```
 */
class TouchableTimeoutCancellation implements Cancellation
{

    use ForbidCloning;
    use ForbidSerialization;

    private string $watcher;
    private readonly Cancellable $cancellation;

    /**
     * @param float  $timeout Seconds until cancellation is requested.
     * @param string $message Message for TimeoutException. Default is "Operation timed out".
     */
    public function __construct(private float $timeout, private string $message = "Operation timed out")
    {
        $this->cancellation = new Cancellable;
        $this->touch();
    }

    /**
     * Cancels the delay watcher.
     */
    public function __destruct()
    {
        EventLoop::cancel($this->watcher);
    }

    public function subscribe(\Closure $callback): string
    {
        return $this->cancellation->subscribe($callback);
    }

    public function unsubscribe(string $id): void
    {
        $this->cancellation->unsubscribe($id);
    }

    public function isRequested(): bool
    {
        return $this->cancellation->isRequested();
    }

    public function throwIfRequested(): void
    {
        $this->cancellation->throwIfRequested();
    }

    /**
     * Touch to reset timeout timer
     */
    public function touch()
    {
        if (isset($this->watcher)) {
            EventLoop::cancel($this->watcher);
        }

        $this->watcher = EventLoop::delay($this->timeout, function(): void {
            $this->cancellation->cancel(new TimeoutException($this->message));
        });

        EventLoop::unreference($this->watcher);
    }

}
