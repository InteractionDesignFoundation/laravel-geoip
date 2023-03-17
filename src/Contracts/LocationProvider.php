<?php

namespace InteractionDesignFoundation\GeoIP\Contracts;

use InteractionDesignFoundation\GeoIP\LocationResponse;

interface LocationProvider
{
    /** The "booting" method of the service. */
    public function boot(): void;

    /** Determine a location based off of the provided IP address. */
    public function locate(string $ip): LocationResponse;
}