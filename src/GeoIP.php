<?php

namespace InteractionDesignFoundation\GeoIP;

use Exception;
use InteractionDesignFoundation\GeoIP\Contracts\LocationProvider;
use Monolog\Level;
use Monolog\Logger;
use Illuminate\Support\Arr;
use Illuminate\Cache\CacheManager;
use Monolog\Handler\StreamHandler;

class GeoIP
{
    /** Illuminate config repository instance. */
    protected array $config = [];

    /** Remote Machine IP address. */
    protected string $remote_ip;

    /** Current location instance. */
    protected Location $location;

    /** Currency data. */
    protected array $currencies = [];

    /** GeoIP service instance. */
    protected ?LocationProvider $service = null;

    /** Cache manager instance. */
    protected CacheManager | Cache $cache;

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
     * @param array        $config
     * @param CacheManager $cache
     */
    public function __construct(array $config, CacheManager $cache)
    {
        $this->config = $config;

        // Create caching instance
        $this->cache = new Cache(
            $cache,
            $this->config('cache_tags'),
            $this->config('cache_expires', 30)
        );

        $defaultLocation = $this->config('default_location', []);
        assert(is_array($defaultLocation));

        // Set custom default location
        $this->default_location = array_merge(
            $this->default_location,
            $defaultLocation
        );

        // Set IP
        $this->remote_ip = $this->default_location['ip'] = $this->getClientIP();
    }

    /**
     * Get the location from the provided IP.
     *
     * @throws \Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getLocation(string $ip = null): Location
    {
        // Get location data
        $this->location = $this->find($ip);

        // Should cache location
        if ($this->shouldCache($this->location, $ip)) {
            $this->getCache()->set($ip, $this->location);
        }

        return $this->location;
    }

    /**
     * Find location from IP.
     *
     * @throws \Exception
     */
    private function find(string $ip = null): Location
    {
        // If IP not set, user remote IP
        $ip = $ip ?: $this->remote_ip;

        // Check cache for location
        if ($this->config('cache', 'none') !== 'none' && $location = $this->getCache()->get($ip)) {
            $location->cached = true;

            return $location;
        }

        // Check if the ip is not local or empty
        if (! $this->isValid($ip)) {
            return $this->getService()->hydrate($this->default_location);
        }

        try {
            // Find location
            $location = $this->getService()->locate($ip);

            // Set currency if not already set by the service
            if (! $location->currency) {
                $location->currency = $this->getCurrency($location->iso_code);
            }

            // Set default
            $location->default = false;

            return $location;
        } catch (\Exception $e) {
            if ($this->config('log_failures', true) === true) {
                $log = new Logger('geoip');
                $log->pushHandler(new StreamHandler(storage_path('logs/geoip.log'), Level::Error));
                $log->error($e);
            }
        }

        return $this->getService()->hydrate($this->default_location);
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
        if ($this->currencies === [] && $this->config('include_currency', false)) {
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
        $config = $this->config('services.' . $this->config('service'), []);

        // Get service class
        $class = Arr::pull($config, 'class');

        // Sanity check
        if ($class === null) {
            throw new \Exception('The GeoIP service is not valid.');
        }

        // Create service instance
        $service = new $class($config);
        assert($service instanceof LocationProvider);
        $this->service = $service;

        return $this->service;
    }

    /**
     * Get cache instance.
     *
     * @return \InteractionDesignFoundation\GeoIP\Cache
     */
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

    /**
     * Determine if the location should be cached.
     *
     * @param Location    $location
     * @param string|null $ip
     *
     * @return bool
     */
    private function shouldCache(Location $location): bool
    {
        if ($location->default || $location->cached) {
            return false;
        }

        return match ($this->config('cache', 'none')) {
            'all', 'some' => true,
            default => false,
        };
    }

    /**
     * Get configuration value.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function config(string $key, $default = null)
    {
        return Arr::get($this->config, $key, $default);
    }
}
