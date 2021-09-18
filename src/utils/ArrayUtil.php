<?php

namespace Wind\Utils;

class ArrayUtil
{

    public static function intersectKeys($array, $keys)
    {
        return array_intersect_key($array, array_flip($keys));
    }

    public static function removeElement(&$arr, $element, $strict=false)
    {
        $i = array_search($element, $arr, $strict);
        if ($i !== false) {
            unset($arr[$i]);
        }
    }

}
