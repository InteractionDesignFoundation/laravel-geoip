<?php

declare(strict_types=1);

if (!function_exists('geoip')) {
    /**
     * Get the location of the provided IP.
     *
     * @param string $ip
     *
     * @return \InteractionDesignFoundation\GeoIP\GeoIP|\InteractionDesignFoundation\GeoIP\Location
     */
    function geoip($ip = null)
    {
        if (is_null($ip)) {
            return app('geoip');
        }

        return app('geoip')->getLocation($ip);
    }
}
