<?php declare(strict_types=1);

if (!function_exists('geoip')) {
    /**
     * Get the location of the provided IP.
     * @param string|null $ip
     * @return \InteractionDesignFoundation\GeoIP\GeoIP|\InteractionDesignFoundation\GeoIP\Location
     * @psalm-return ($ip is null ? \InteractionDesignFoundation\GeoIP\GeoIP : \InteractionDesignFoundation\GeoIP\Location)
     * @throws \Exception
     */
    function geoip($ip = null)
    {
        if ($ip === null) {
            return app('geoip');
        }

        return app('geoip')->getLocation($ip);
    }
}
