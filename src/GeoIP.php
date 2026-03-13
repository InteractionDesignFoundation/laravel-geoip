<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP;

use Illuminate\Cache\CacheManager;
use Illuminate\Support\Arr;
use League\ISO3166\Exception\OutOfBoundsException;
use League\ISO3166\ISO3166;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type LocationArray from \InteractionDesignFoundation\GeoIP\Location
 */
class GeoIP
{
    /**
     * Current location instance.
     *
     * @var Location|null
     */
    protected $location = null;

    protected ?ISO3166 $iso3166 = null;

    /**
     * GeoIP service instance.
     *
     * @var Contracts\ServiceInterface
     */
    protected $service;

    /** Cache manager instance. */
    protected \InteractionDesignFoundation\GeoIP\Cache $cache;

    /** Default Location data. */
    protected array $default_location = [
        'ip' => '127.0.0.0',
        'iso_code' => 'US',
        'country' => 'United States',
        'city' => 'New Haven',
        'state' => 'CT',
        'state_name' => 'Connecticut',
        'postal_code' => '06510',
        'lat' => 41.31,
        'lon' => -72.92,
        'timezone' => 'America/New_York',
        'continent' => 'NA',
        'currency' => 'USD',
        'default' => true,
        'cached' => false,
    ];

    /**
     * Create a new GeoIP instance.
     *
     * @param array $config
     * @param CacheManager $cache
     */
    public function __construct(
        protected array $config,
        CacheManager $cache,
        private readonly LoggerInterface $logger,
    ) {
        // Create caching instance
        $this->cache = new Cache(
            $cache,
            $this->config('cache_tags'),
            $this->config('cache_expires', 30)
        );
        $this->cache->setPrefix((string) $this->config('cache_prefix'));

        // Set custom default location
        $this->default_location = array_merge(
            $this->default_location,
            $this->config('default_location', [])
        );

        // Set IP
        $this->default_location['ip'] = $this->getClientIP();
    }

    /**
     * Get the location from the provided IP.
     *
     * @param string $ip
     *
     * @return \InteractionDesignFoundation\GeoIP\Location
     * @throws \Exception
     */
    public function getLocation($ip = null)
    {
        // Get location data
        $this->location = $this->find($ip);

        // Should cache location
        if ($this->shouldCache($this->location, $ip)) {
            $cacheKey = $ip ?? (string) $this->location->ip;
            $this->getCache()->set($cacheKey, $this->location);
        }

        return $this->location;
    }

    /**
     * Find location from IP.
     * @return \InteractionDesignFoundation\GeoIP\Location
     * @throws \Exception
     */
    private function find(?string $ip = null): Location
    {
        // If IP not set, user remote IP
        $ip = $ip ?: $this->getClientIP();

        // Check cache for location
        if ($this->config('cache', 'none') !== 'none' && $location = $this->getCache()->get($ip)) {
            $location = $location->withAttribute('cached', true);

            return $location;
        }

        // Check if the ip is not local or empty
        if ($this->isValid($ip)) {
            try {
                // Find location
                $location = $this->getService()->locate($ip);

                // Set currency if not already set by the service
                if (! $location->currency) {
                    $location = $location->withAttribute('currency', $this->getCurrency($location->iso_code));
                }

                // Set default
                $location = $location->withAttribute('default', false);

                return $location;
            } catch (\Exception $e) {
                if ($this->config('log_failures', true) === true) {
                    $this->logger->error('GeoIP lookup failed', [
                        'exception' => $e,
                    ]);
                }
            }
        }

        return $this->getService()->hydrate($this->default_location);
    }

    /**
     * Get the currency code from ISO.
     *
     * @param string $iso
     *
     * @return string|null
     */
    public function getCurrency(?string $iso)
    {
        if ($iso === null || ! $this->config('include_currency', false)) {
            return null;
        }

        try {
            $this->iso3166 ??= new ISO3166();
            $country = $this->iso3166->alpha2($iso);

            return $country['currency'][0] ?? null;
        } catch (OutOfBoundsException) {
            return null;
        }
    }

    /**
     * Get service instance.
     *
     * @throws \InvalidArgumentException
     */
    public function getService(): Contracts\ServiceInterface
    {
        if ($this->service === null) {
            // Get service configuration
            $config = $this->config('services.'.$this->config('service'), []);

            // Get service class
            $class = Arr::pull($config, 'class');

            if ($class === null || ! is_string($class)) {
                throw new \InvalidArgumentException('No GeoIP service is configured.');
            }

            if (! is_subclass_of($class, Contracts\ServiceInterface::class)) {
                throw new \InvalidArgumentException(sprintf(
                    'GeoIP service [%s] must implement %s.',
                    $class,
                    Contracts\ServiceInterface::class
                ));
            }

            // Create service instance
            $this->service = new $class($config);
        }

        return $this->service;
    }

    /**
     * Get cache instance.
     *
     * @return \InteractionDesignFoundation\GeoIP\Cache
     */
    public function getCache(): \InteractionDesignFoundation\GeoIP\Cache
    {
        return $this->cache;
    }

    /**
     * Get the client IP address.
     *
     * Delegates to Laravel's Request::ip() which respects
     * the TrustProxies middleware configuration.
     */
    public function getClientIP(): string
    {
        $request = request();

        return $request->ip() ?? '127.0.0.0';
    }

    /**
     * Checks if the ip is valid.
     *
     * @param string $ip
     *
     * @return bool
     */
    private function isValid(string $ip): bool
    {
        return !(! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE));
    }

    /**
     * Determine if the location should be cached.
     *
     * @param Location $location
     * @param string|null $ip
     *
     * @return bool
     */
    private function shouldCache(Location $location, ?string $ip = null): bool
    {
        if ($location->default === true || $location->cached === true) {
            return false;
        }

        return match ($this->config('cache', 'none')) {
            'all' => true,
            'some' => $ip !== null,
            default => false,
        };
    }

    /**
     * Get configuration value.
     *
     * @param string $key
     * @param array|bool|int|null|string $default
     *
     * @return mixed
     */
    public function config($key, mixed $default = null)
    {
        return Arr::get($this->config, $key, $default);
    }
}
