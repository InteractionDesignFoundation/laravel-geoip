<?php

declare(strict_types=1);

if (!function_exists('storage_path')) {
    function storage_path($path = '')
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'tmp' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('env')) {
    function env($key, $default = null)
    {
        return $key;
    }
}

if (!function_exists('app')) {
    function app($key = null, $default = null)
    {
        return \InteractionDesignFoundation\GeoIP\Tests\TestCase::$functions->app($key, $default);
    }
}
