<?php

namespace Wind\Base;

use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;
use Amp\TimeoutException;

/**
 * Touchable Timeout
 *
 * Example:
 * ```
 * ```
 */
final class TouchableTimeout
{

    /**
     * @var Deferred
     */
    private $deferred;

    /**
     * @var string
     */
    private $watcher;

    /**
     * @var int
     */
    private $timeout;

    /**
     * @var \Closure
     */
    private $completeCallback;

    public function __construct(Promise $promise, int $timeout)
    {
        $this->timeout = $timeout;
        $this->deferred = new Deferred;

        $this->touch();

        $promise->onResolve(function() use ($promise) {
            if ($this->deferred) {
                Loop::cancel($this->watcher);
                $this->complete();
                $this->deferred->resolve($promise);
                $this->deferred = null;
            }
        });
    }

    /**
     * Re-watch timeout
     */
    public function touch()
    {
        if ($this->watcher) {
            Loop::cancel($this->watcher);
        }

        $this->watcher = Loop::delay($this->timeout, function() {
            $temp = $this->deferred; // prevent double resolve
            $this->deferred = null;
            $this->complete();
            $temp->fail(new TimeoutException);
        });

        Loop::unreference($this->watcher);
    }

    public function onComplete(\Closure $callback)
    {
        $this->completeCallback = $callback;
    }

    private function complete()
    {
        if ($this->completeCallback) {
            call_user_func($this->completeCallback);
            $this->completeCallback = null;
        }
    }

    /**
     * You should immediately call promise() after create TouchableTimeout instance.
     */
    public function promise()
    {
        return $this->deferred->promise();
    }

}
