<?php

if (!function_exists('str_contains')) {
    function str_contains($str, $search) {
        return strpos($str, $search) !== false;
    }
}
