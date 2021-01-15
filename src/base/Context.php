<?php

namespace Wind\Base;

use Workerman\Protocols\Http\Request;

/**
 * Class Context
 * @package Wind\Base
 *
 * @property Request $request
 * @property array $vars
 */
class Context
{

    private $container = [];

    public function __get($name)
    {
        if (isset($this->container[$name])) {
            return $this->container[$name];
        } else {
            throw new \Exception("No found '$name' in Context container.");
        }
    }

    public function __set($name, $value)
    {
        $this->container[$name] = $value;
    }

}