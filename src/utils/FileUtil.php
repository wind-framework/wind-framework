<?php

namespace Wind\Utils;

class FileUtil
{

    public static function formatSize($filesize)
    {
        $units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        if ($filesize) {
            $i = floor(log($filesize, 1024));
            return number_format($filesize/pow(1024, $i), 2, '.', '').' '.$units[$i];
        } else {
            return '0 Bytes';
        }
    }

}