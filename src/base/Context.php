<?php

declare(strict_types=1);

namespace Wind\Base;

use Revolt\EventLoop\FiberLocal;
use Workerman\Protocols\Http\Request;

/**
 * Wind Context
 *
 * Store data in each separately coroutine context.
 *
 * @package Wind\Base
 *
 * @property Request $request
 * @property array $vars
 */
class Context
{

    private static FiberLocal $local;

    /**
     * Initialize context storage
     * @return FiberLocal
     */
    public static function init(): FiberLocal
    {
        return self::$local ??= new FiberLocal(static fn() => new \stdClass());
    }

    /**
     * Get value from current context
     *
     * @param string $name
     * @return mixed
     */
    public static function get($name)
    {
        $object = self::$local?->get();

        if ($object !== null && property_exists($object, $name)) {
            return $object->$name;
        } else {
            throw new \Exception("Undefined name '$name' in current context.");
        }
    }

    /**
     * Set value of name in current context
     *
     * @param string $name
     * @param mixed $value
     */
    public static function set($name, $value)
    {
        $object = self::init()->get();
        $object->$name = $value;
    }

    /**
     * Unset value of name in current context
     *
     * @param string $name
     */
    public static function unset($name)
    {
        $object = self::init()->get();
        unset($object->$name);
    }

    /**
     * Clear data in current context
     */
    public static function clear()
    {
        self::$local?->clear();
    }

}
