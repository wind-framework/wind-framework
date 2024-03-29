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

    private static ?FiberLocal $local = null;

    /**
     * Check name exists in current context
     *
     * @param string $name
     * @return bool
     */
    public static function has($name)
    {
        return self::$local !== null && property_exists(self::$local->get(), $name);
    }

    /**
     * Get value from current context
     *
     * @param string $name
     * @return mixed
     * @throws \Exception
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
     * Get value from current context or return default
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public static function getOrDefault($name, $default=null)
    {
        $object = self::$local?->get();

        if ($object !== null && property_exists($object, $name)) {
            return $object->$name;
        } else {
            return $default;
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
	    self::$local ??= new FiberLocal(static fn() => new \stdClass());
        $object = self::$local->get();
        $object->$name = $value;
    }

    /**
     * Unset value of name in current context
     *
     * @param string $name
     */
    public static function unset($name)
    {
		if (self::$local !== null) {
			$object = self::$local->get();
			unset($object->$name);
		}
    }

    /**
     * Clear data in current context
     */
    public static function clear()
    {
        self::$local?->clear();
    }

}
