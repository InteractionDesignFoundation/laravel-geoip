<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP;

use Illuminate\Support\Arr;
use Illuminate\Cache\CacheManager;

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

    /**
     * Currency data.
     *
     * @var array<string, string>|null
     */
    protected $currencies = null;

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
    public function __construct(protected array $config, CacheManager $cache)
    {
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
            $this->getCache()->set($ip, $this->location);
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
            $location->cached = true;

            return $location;
        }

        // Check if the ip is not local or empty
        if ($this->isValid($ip)) {
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
                    if (! class_exists(\Monolog\Logger::class)) {
                        throw new \RuntimeException(
                            'monolog/monolog composer package is not installed, but required with the enabled geoip.log_failures config option.',
                            0,
                            $e
                        );
                    }

                    $log = new \Monolog\Logger('geoip');
                    $log->pushHandler(new \Monolog\Handler\StreamHandler(storage_path('logs/geoip.log'), \Monolog\Logger::ERROR));
                    $log->error($e);
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
    public function getCurrency($iso)
    {
        if ($this->currencies === null && $this->config('include_currency', false)) {
            $this->currencies = include(__DIR__ . '/Support/Currencies.php');
        }

        return Arr::get($this->currencies, $iso);
    }

    /**
     * Get service instance.
     *
     * @return \InteractionDesignFoundation\GeoIP\Contracts\ServiceInterface
     * @throws \Exception
     */
    public function getService()
    {
        if ($this->service === null) {
            // Get service configuration
            $config = $this->config('services.' . $this->config('service'), []);

            // Get service class
            $class = Arr::pull($config, 'class');

            // Sanity check
            if ($class === null) {
                throw new \Exception('No GeoIP service is configured.');
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
     * @return string
     */
    public function getClientIP(): string
    {
        /** @see \Symfony\Component\HttpKernel\HttpCache\SubRequestHandler */
        $remotes_keys = [
            'HTTP_X_FORWARDED_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_CF_CONNECTING_IP',
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
     * @psalm-assert-if-true string $ip
     */
    private function shouldCache(Location $location, ?string $ip = null): bool
    {
        if ($ip === null) {
            return false;
        }

        if ($location->default === true || $location->cached === true) {
            return false;
        }

        return match ($this->config('cache', 'none')) {
            'all', 'some' && $ip === null => true,
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
