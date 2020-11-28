<?php

namespace Framework\Utils;

class StrUtil
{

    /**
     * Generate random string
     * 
     * @param int $length
     * @return string
     */
    public static function randomString($length)
    {
        static $range = [48, 57, 65, 90, 97, 122];
        $str = '';
        for ($i=0; $i<$length; $i++) {
			$s = \mt_rand(0, 2) * 2;
            $str .= \chr(\mt_rand($range[$s], $range[$s+1]));
        }
        return $str;
    }
    
}