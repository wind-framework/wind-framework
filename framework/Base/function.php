<?php

if (!function_exists('str_contains')) {
    function str_contains($str, $search) {
        return strpos($str, $search) !== false;
    }
}

function getApp() {
    return \Framework\Base\Application::getInstance();
}

/**
 * 等于普通模式的 exit() 函数
 *
 * @param int $code
 * @throws Exception
 */
function done($code=0) {
    throw new Exception('', $code);
}