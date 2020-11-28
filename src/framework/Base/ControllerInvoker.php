<?php

namespace Framework\Base;

use Invoker\InvokerInterface;

class ControllerInvoker
{

    /**
     * @var InvokerInterface
     */
    private $invoker;

    public function __construct(InvokerInterface $invoker)
    {
        $this->invoker = $invoker;
    }

    /**
     * @inheritDoc
     */
    public function __invoke($callable, array $parameters = [])
    {
        // TODO: Implement call() method.
    }
}