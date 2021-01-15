<?php

namespace Wind\Web;

abstract class Controller
{

    /**
     * @return void|\Amp\Promise|\Generator
     */
    public function init()
    {}

}