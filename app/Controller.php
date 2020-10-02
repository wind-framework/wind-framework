<?php

namespace App;

abstract class Controller
{

    /**
     * @return void|\Amp\Promise|\Generator
     */
    public function init()
    {}

}