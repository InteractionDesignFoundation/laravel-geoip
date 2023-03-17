<?php

namespace InteractionDesignFoundation\GeoIP;

use Exception;
use InteractionDesignFoundation\GeoIP\Contracts\LocationProvider;
use Monolog\Level;
use Monolog\Logger;
use Illuminate\Support\Arr;
use Illuminate\Cache\CacheManager;
use Monolog\Handler\StreamHandler;

final class GeoIP
{
    /** Remote Machine IP address. */
    protected string $remote_ip;

    /** Current location instance. */
    protected LocationResponse $location;

    /**
     * Currency data.
     *
     * @var array<string, string> $currencies
     */
    protected array $currencies = [];

    /** GeoIP service instance. */
    protected ?LocationProvider $service = null;

    /** Cache manager instance. */
    protected CacheManager | Cache $cache;

    /** Default Location data. */
    protected LocationResponse $defaultLocation;

    public function __construct(CacheManager $cache)
    {
        $this->setDefaultLocation();

        $cacheExpires = config('geoip.cache_expires', 30);
        $cacheTags = config('geoip.cache_tags');
        assert(is_array($cacheTags) && is_int($cacheExpires));

        $this->cache = new Cache($cache, $cacheTags, $cacheExpires);

        $this->remote_ip = $this->getClientIP();
    }

    /**
     * Get the location from the provided IP.
     *
     * @throws \Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getLocation(string $ip = null): LocationResponse
    {
        // Get location data
        $this->location = $this->find($ip);

        // Should cache location
        if ($this->shouldCacheLocation($this->location)) {
            $this->getCache()->set((string) $ip, $this->location);
        }

        return $this->location;
    }

    /**
     * Find location from IP.
     *
     * @throws \Exception
     */
    private function find(string $ip = null): LocationResponse
    {
        // If IP not set, user remote IP
        $ip = $ip ?: $this->remote_ip;

        // Check cache for location
        if ($this->shouldCache() && $location = $this->getCache()->get($ip)) {
            assert($location instanceof LocationResponse);
            $location->setCached(true);

            return $location;
        }

        // Check if the ip is not local or empty
        if (! $this->isValid($ip)) {
            return $this->defaultLocation;
        }

        try {
            // Find location
            $location = $this->getService()->locate($ip);

            // Set currency if not already set by the service
            if (! $location->currency) {
                $location->setCurrency($this->getCurrency($location->country));
            }

            // Set default
            $location->setDefault(false);

            return $location;
        } catch (\Exception $e) {
            $shouldLogFailures = config('geoip.log_failures', true);
            if ($shouldLogFailures === true) {
                $log = new Logger('geoip');
                $log->pushHandler(new StreamHandler(storage_path('logs/geoip.log'), Level::Error));
                $log->error($e);
            }
        }

        return $this->defaultLocation;
    }

    /**
     * Get the currency code from ISO.
     *
     * @param string $iso
     *
     * @return string
     */
    public function getCurrency(string $iso): string
    {
        $shouldIncludeCurrency = config('geoip.include_currency', false);
        if ($this->currencies === [] && $shouldIncludeCurrency) {
            $this->currencies = include(__DIR__ . '/Support/Currencies.php');
        }

        $currency = Arr::get($this->currencies, $iso);
        assert(is_string($currency));

        return $currency;
    }

    /**
     * Get service instance.
     *
     * @throws Exception
     * @return \InteractionDesignFoundation\GeoIP\Contracts\LocationProvider
     */
    public function getService(): LocationProvider
    {
        if ($this->service instanceof LocationProvider) {
            return $this->service;
        }

        // Get service configuration
        $availableServices = config('geoip.services');

        if (! is_array($availableServices)) {
            throw new \RuntimeException('The GeoIP services are not valid.');
        }

        $currentService = config('geoip.service');
        assert(is_string($currentService));

        // Get service class
        $currentServiceConfig = config("geoip.services.$currentService");
        assert(is_array($currentServiceConfig));
        $class = $currentServiceConfig['class'];

        // Sanity check
        if (! is_subclass_of($class, LocationProvider::class)) {
            throw new \RuntimeException('The GeoIP service is not valid.');
        }

        // Create service instance
        $service = new $class();
        assert($service instanceof LocationProvider);
        $this->service = $service;

        return $this->service;
    }

    /** Get cache instance. */
    public function getCache(): CacheManager | Cache
    {
        return $this->cache;
    }

    /**
     * Get the client IP address.
     *
     * @return string
     */
    public function getClientIP(): string
    {
        $remotes_keys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
            'HTTP_X_CLUSTER_CLIENT_IP',
        ];

        foreach ($remotes_keys as $key) {
            if ($address = getenv($key)) {
                foreach (explode(',', $address) as $ip) {
                    if ($this->isValid($ip)) {
                        return $ip;
                    }
                }
            }
        }

        return '127.0.0.0';
    }

    /** Checks if the ip is valid. */
    private function isValid(string $ip): bool
    {
        return ! (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE));
    }

    /** Determine if the location should be cached. */
    private function shouldCacheLocation(LocationResponse $location): bool
    {
        if ($location->default || $location->cached) {
            return false;
        }

        return $this->shouldCache();
    }

    private function shouldCache(): bool
    {
        $cacheConfig = config('geoip.cache', 'none');

        return match ($cacheConfig) {
            'all', 'some' => true,
            default => false,
        };
    }


    private function setDefaultLocation(): void
    {
        $defaultLocation = config('geoip.default_location');
        if ($defaultLocation instanceof LocationResponse) {
            $this->defaultLocation = $defaultLocation;
            return;
        }

        $this->defaultLocation = new LocationResponse(
            '127.0.0.0',
            'US',
            'United States',
            'New Haven',
            'CT',
            'Connecticut',
            '06510',
            41.31,
            -72.92,
            'America/New_York',
            'NA',
            'USD',
            true,
            false,
        );
    }
}
