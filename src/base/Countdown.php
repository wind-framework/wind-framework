<?php

namespace Wind\Base;

use Amp\DeferredFuture;

/**
 * Countdown
 *
 * Resolved when $count countdown to 0.
 *
 * @package Wind\Base
 */
class Countdown
{

    private $count = 0;
    private $deferred;

    public function __construct(int $count)
    {
        if ($count < 1) {
            throw new \InvalidArgumentException("Countdown \$count must great than, $count given.");
        }
        $this->count = $count;
        $this->deferred = new DeferredFuture();
    }

    /**
     * Make it countdown
     *
     * @return int Current count after countdown
     */
    public function countdown()
    {
        if ($this->count > 0 && --$this->count == 0) {
            $this->deferred->complete(null);
        }
        return $this->count;
    }

    public function count()
    {
        return $this->count;
    }

    /**
     * Countdown future resolve when count to 0
     *
     * @return \Amp\Future
     */
    public function getFuture()
    {
        return $this->deferred->getFuture();
    }

}
