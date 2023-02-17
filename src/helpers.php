<?php

if (!function_exists('geoip')) {
    /** Get the location of the provided IP. */
    function geoip(string $ip = null): \InteractionDesignFoundation\GeoIP\Location|\InteractionDesignFoundation\GeoIP\LocationResponse
    {
        if (is_null($ip)) {
            return app('geoip');
        }

        return app('geoip')->getLocation($ip);
    }
}