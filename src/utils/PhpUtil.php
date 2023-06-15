<?php

namespace Wind\Utils;

/**
 * Wind Php Utilities
 */
class PhpUtil {

    /**
     * Check does class has trait inside
     *
     * @param string $class
     * @param string $trait
     * @return bool true if class or parents used trait, false otherwise.
     */
    public static function hasTrait(string $class, string $trait)
    {
        do {
            $traits = class_uses($class);
            if ($traits) {
                if (in_array($trait, $traits)) {
                    return true;
                } else {
                    //check trait in trait
                    foreach ($traits as $traitClass) {
                        if (self::hasTrait($traitClass, $trait)) {
                            return true;
                        }
                    }
                }
            }
        } while ($class = get_parent_class($class));

        return false;
    }

}
