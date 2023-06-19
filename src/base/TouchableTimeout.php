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

    private $promise;

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
        $this->promise = $promise;
        $this->deferred = new Deferred;
    }

    /**
     * Re-watch timeout
     */
    public function touch()
    {
        if (!$this->deferred) {
            return;
        }

        if ($this->watcher) {
            Loop::cancel($this->watcher);
        }

        $this->watcher = Loop::delay($this->timeout, function() {
            $temp = $this->deferred; // prevent double resolve
            $this->deferred = $this->promise = $this->watcher = null;
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
     * Get timeout Promise to wait
     */
    public function promise(): Promise
    {
        $this->touch();
        $promise = $this->deferred->promise();

        $this->promise->onResolve(function() {
            if ($this->deferred) {
                Loop::cancel($this->watcher);
                $this->complete();
                $this->deferred->resolve($this->promise);
                $this->deferred = $this->promise = $this->watcher = null;
            }
        });

        return $promise;
    }

}
