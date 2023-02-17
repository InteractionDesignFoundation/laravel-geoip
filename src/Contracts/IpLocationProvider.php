<?php

namespace InteractionDesignFoundation\GeoIP\Contracts;

use InteractionDesignFoundation\GeoIP\Location;
use InteractionDesignFoundation\GeoIP\LocationResponse;

interface IpLocationProvider
{
    /** The "booting" method of the service. */
    public function boot(): void;

    /** Determine a location based off of the provided IP address. */
    public function locate(string $ip): Location|LocationResponse;

    /** Create a location instance from the provided attributes. */
    public function hydrate(array $attributes = []): Location|LocationResponse;

    /**
     * Get configuration value.
     *
     * @param mixed  $default
     *
     * @return mixed
     */
    public function config(string $key, $default = null);
}