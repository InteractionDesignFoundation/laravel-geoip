<?php

if (!function_exists('geoip')) {
    /**
     * Get the location of the provided IP.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    function geoip(string $ip = null): \InteractionDesignFoundation\GeoIP\GeoIP|\InteractionDesignFoundation\GeoIP\LocationResponse
    {
        /** @var \InteractionDesignFoundation\GeoIP\GeoIP $geoip */
        $geoip = app('geoip');
        if (is_null($ip)) {
            return $geoip;
        }

        return $geoip->getLocation($ip);
    }
}