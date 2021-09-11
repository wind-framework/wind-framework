<?php

namespace Wind\Base;

use Amp\Deferred;

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
        $this->deferred = new Deferred();
    }

    /**
     * Make it countdown
     *
     * @return int Current count after countdown
     */
    public function countdown()
    {
        if ($this->count > 0 && --$this->count == 0) {
            $this->deferred->resolve();
        }
        return $this->count;
    }

    public function count()
    {
        return $this->count;
    }

    /**
     * Countdown promise resolve when count to 0
     *
     * @return \Amp\Promise
     */
    public function promise()
    {
        return $this->deferred->promise();
    }

}
