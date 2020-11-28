<?php

namespace Framework\Base;

abstract class Controller
{

    /**
     * @return void|\Amp\Promise|\Generator
     */
    public function init()
    {}

}