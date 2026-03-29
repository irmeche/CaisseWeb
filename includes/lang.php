<?php
if (!function_exists('__')) {
    function __($key) {
        static $strings = null;
        if ($strings === null) {
            $file    = dirname(__FILE__) . '/../lang/fr.php';
            $strings = file_exists($file) ? require $file : array();
        }
        return isset($strings[$key]) ? $strings[$key] : $key;
    }
}
