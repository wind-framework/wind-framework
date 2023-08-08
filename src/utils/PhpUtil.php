<?php

namespace Wind\Utils;

/**
 * Wind Php Utilities
 */
class PhpUtil {

    /**
     * Check does class has trait inside
     *
     * @param class-string $class
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

    /**
     * Get class and all parents traits
     *
     * @param object|class-string $class
     * @return array
     */
    public static function getTraits($class)
    {
        $parentClasses = class_parents($class);
        $traits = class_uses($class);

        if ($parentClasses) {
            foreach ($parentClasses as $pClass) {
                $traits = array_merge($traits, class_uses($pClass));
            }
        }

        return array_unique($traits);
    }

}
