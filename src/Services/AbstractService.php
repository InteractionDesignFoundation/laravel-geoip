<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use InteractionDesignFoundation\GeoIP\Location;
use Illuminate\Support\Arr;
use InteractionDesignFoundation\GeoIP\Contracts\IpLocationProvider;
use InteractionDesignFoundation\GeoIP\LocationResponse;

abstract class AbstractService implements IpLocationProvider
{
    /** Create a new service instance. */
    public function __construct(protected array $config = [])
    {
        $this->boot();
    }

    /** The "booting" method of the service. */
    public function boot(): void
    {
        //
    }

    /** @inheritdoc */
    abstract public function hydrate(array $attributes = []): Location|LocationResponse;

    /**
     * Get configuration value.
     *
     * @param mixed  $default
     * @return mixed
     */
    public function config(string $key, $default = null)
    {
        return Arr::get($this->config, $key, $default);
    }
}