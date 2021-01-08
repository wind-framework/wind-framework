<?php

namespace Framework\Web;

abstract class Controller
{

    /**
     * @return void|\Amp\Promise|\Generator
     */
    public function init()
    {}

}