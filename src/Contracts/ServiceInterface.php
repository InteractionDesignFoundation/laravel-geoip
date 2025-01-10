<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Contracts;

/**
 * @psalm-import-type LocationArray from \InteractionDesignFoundation\GeoIP\Location
 */
interface ServiceInterface
{
    /**
     * The "booting" method of the service.
     *
     * @return void
     */
    public function boot();

    /**
     * Determine a location based off of
     * the provided IP address.
     *
     * @param string $ip
     *
     * @return \InteractionDesignFoundation\GeoIP\Location
     *
     * @throws \InvalidArgumentException if an invalid IP address is passed
     */
    public function locate($ip);

    /**
     * Create a location instance from the provided attributes.
     *
     * @param array $attributes
     * @psalm-param LocationArray $attributes
     *
     * @return \InteractionDesignFoundation\GeoIP\Location
     */
    public function hydrate(array $attributes = []);

    /**
     * Get configuration value.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function config($key, mixed $default = null);
}
