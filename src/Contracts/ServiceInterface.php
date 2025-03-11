<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Contracts;

use InteractionDesignFoundation\GeoIP\Location;

/**
 * @psalm-import-type LocationArray from \InteractionDesignFoundation\GeoIP\Location
 */
interface ServiceInterface
{
    /**
     * The "booting" method of the service.
     * @return void
     */
    public function boot(): void;

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
    public function locate($ip): Location;

    /**
     * Create a location instance from the provided attributes.
     *
     * @param array $attributes
     * @psalm-param LocationArray $attributes
     */
    public function hydrate(array $attributes = []): Location;

    /**
     * Get configuration value.
     * @return mixed
     */
    public function config(string $key, mixed $default = null);
}
