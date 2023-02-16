<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use InteractionDesignFoundation\GeoIP\Location;
use Illuminate\Support\Arr;
use InteractionDesignFoundation\GeoIP\Contracts\ServiceInterface;

abstract class AbstractService implements ServiceInterface
{
    /** Driver config */
    protected array $config;

    /** Create a new service instance. */
    public function __construct(array $config = [])
    {
        $this->config = $config;

        $this->boot();
    }

    /** The "booting" method of the service. */
    public function boot(): void
    {
        //
    }

    /** @inheritdoc */
    public function hydrate(array $attributes = []): Location
    {
        return new Location($attributes);
    }

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