<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use InteractionDesignFoundation\GeoIP\Location;
use Illuminate\Support\Arr;
use InteractionDesignFoundation\GeoIP\Contracts\ServiceInterface;
use InteractionDesignFoundation\GeoIP\Exceptions\MissingConfigurationException;

abstract class AbstractService implements ServiceInterface
{
    /**
     * Driver config
     *
     * @var array
     */
    protected $config;

    /**
     * Create a new service instance.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;

        $this->boot();
    }

    /**
     * The "booting" method of the service.
     *
     * @return void
     */
    public function boot()
    {
    }

    /** {@inheritDoc} */
    public function hydrate(array $attributes = [])
    {
        return new Location($attributes);
    }

    /**
     * Get configuration value.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function config($key, $default = null)
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * This method ensures that the given key was filled
     * by the user, so that the service can be called without
     * errors raised linked to missing configuration.
     * 
     * @param string|string[] $key
     * @return void
     */
    public function ensureConfigurationParameterDefined($keys) {
        // Be able to accept a string and an array of strings.
        $keys = is_string($keys) ? [$keys] : $keys ;

        foreach($keys as $key) {
            $config = $this->config($key) ;

            // If the config is not defined / is empty.
            if(empty($config)) {
                $service = (new \ReflectionClass($this))->getShortName() ;
                
                throw new MissingConfigurationException("Missing '$key' parameter (service: $service)") ;
            }
        }
    }
}
