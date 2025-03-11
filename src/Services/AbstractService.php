<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use InteractionDesignFoundation\GeoIP\Location;
use InteractionDesignFoundation\GeoIP\Contracts\ServiceInterface;
use InteractionDesignFoundation\GeoIP\Exceptions\MissingConfigurationException;

abstract class AbstractService implements ServiceInterface
{
    /**
     * Create a new service instance.
     *
     * @param array $config
     */
    public function __construct(protected array $config = [])
    {
        $this->boot();
    }

    /** The "booting" method of the service. */
    #[\Override]
    public function boot(): void
    {
    }

    /** {@inheritDoc} */
    #[\Override]
    public function hydrate(array $attributes = []): Location
    {
        return new Location($attributes);
    }

    /**
     * Get configuration value.
     * @return mixed
     */
    #[\Override]
    public function config(string $key, mixed $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * This method ensures that the given key was filled
     * by the user, so that the service can be called without
     * errors raised linked to missing configuration.
     *
     * @param string|list<string> $keys
     * @return void
     */
    public function ensureConfigurationParameterDefined(string | array $keys): void
    {
        // Be able to accept a string and an array of strings.
        $keys = is_string($keys) ? [$keys] : $keys;

        foreach ($keys as $key) {
            $configValue = $this->config($key);

            // If the config is not defined / is empty.
            if (empty($configValue)) {
                $service = (new \ReflectionClass($this))->getShortName();

                throw new MissingConfigurationException(sprintf("Missing '%s' parameter (service: %s)", $key, $service));
            }
        }
    }
}
