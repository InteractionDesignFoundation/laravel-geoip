<?php

use InteractionDesignFoundation\GeoIP\GeoIP;
use InteractionDesignFoundation\GeoIP\Location;
use InteractionDesignFoundation\GeoIP\LocationResponse;

if (!function_exists('geoip')) {
    /** Get the location of the provided IP. */
    function geoip(string $ip = null): Location|LocationResponse|GeoIP
    {
        if (is_null($ip)) {
            return app('geoip');
        }

        return app('geoip')->getLocation($ip);
    }
}