<?php

namespace Framework\Utils;

class ArrayUtil
{

    public static function removeElement(&$arr, $element, $strict=false)
    {
        $i = array_search($element, $arr, $strict);
        if ($i !== false) {
            unset($arr[$i]);
        }
    }

}
