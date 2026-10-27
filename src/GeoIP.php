<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP;

use GeoIp2\Exception\AddressNotFoundException;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Arr;
use League\ISO3166\Exception\OutOfBoundsException;
use League\ISO3166\ISO3166;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type LocationArray from \InteractionDesignFoundation\GeoIP\Location
 * @psalm-api
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
     * @var Contracts\ServiceInterface|null
     */
    protected $service;

    /** Cache manager instance. */
    protected \InteractionDesignFoundation\GeoIP\Cache $cache;

    /**
     * Default Location data.
     *
     * @var LocationArray
     */
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
        /** @var list<string> $cacheTags */
        $cacheTags = array_values((array) $this->config('cache_tags', []));
        if ($cacheTags !== [] && !$cache->supportsTags()) {
            $this->logger->info('GeoIP: cache tags are configured but the active cache driver does not support tagging. Tags will be ignored.');
        }

        $this->cache = new Cache(
            $cache,
            $cacheTags,
            (int) $this->config('cache_expires', 30)
        );
        $this->cache->setPrefix((string) $this->config('cache_prefix'));

        // Set custom default location
        /** @var LocationArray $defaultLocation */
        $defaultLocation = array_merge($this->default_location, (array) $this->config('default_location', []));
        $this->default_location = $defaultLocation;

        // Set IP
        $this->default_location['ip'] = $this->getClientIP();
    }

    /**
     * Get the location from the provided IP.
     *
     * @param string $ip
     *
     * @return \InteractionDesignFoundation\GeoIP\Location
     * @throws \InvalidArgumentException if an explicitly provided $ip is not a syntactically valid IP address
     * @throws \Exception
     */
    public function getLocation($ip = null)
    {
        if ($ip !== null) {
            $ip = $this->normalizeIp($ip);

            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) === false) {
                throw new \InvalidArgumentException('The provided value is not a valid IP address.');
            }
        }

        // Get location data
        $this->location = $this->find($ip, $ip !== null);

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
    private function find(?string $ip, bool $explicitIp): Location
    {
        // If IP not set, user remote IP
        $ip = $this->normalizeIp($ip === null || $ip === '' ? $this->getClientIP() : $ip);

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
                if ($location->currency === null || $location->currency === '') {
                    $location = $location->withAttribute('currency', $this->getCurrency($location->iso_code));
                }

                // Set default
                $location = $location->withAttribute('default', false);

                return $location;
            } catch (AddressNotFoundException) {
                // Expected miss, not a failure. Unlike transient errors it is deterministic, so it is negatively cached.
                if ($this->config('log_failures', true) === true) {
                    $this->logger->debug('GeoIP address not found', ['ip' => $ip, 'service' => $this->getService()::class]);
                }

                $location = $this->getService()->hydrate(['ip' => $ip] + $this->default_location);

                if ($this->cacheAllows($explicitIp)) {
                    $this->getCache()->set($ip, $location, (int) ($this->config('cache_not_found_expires') ?? $this->config('cache_expires', 30)));
                }

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
            $config = (array) $this->config('services.'.(string) $this->config('service'), []);
            // Per-service values win over the package-wide ones.
            $config += ['timeout' => $this->config('timeout'), 'connect_timeout' => $this->config('connect_timeout')];

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

            // Injected after construction so custom services keep their own constructor signature.
            if ($this->service instanceof LoggerAwareInterface) {
                $this->service->setLogger($this->logger);
            }
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
     * Canonicalize an IPv6 address and unwrap IPv4-mapped ones (::ffff:a.b.c.d or ::ffff:abcd:ef01) to plain IPv4.
     * GLOBAL_RANGE rejects all of ::ffff:0:0/96 and any IPv6 with a dotted IPv4 suffix (64:ff9b::8.8.8.8),
     * so the address is validated, looked up and cached in its canonical form. Other input is returned as is.
     *
     * @psalm-pure
     */
    private function normalizeIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $ip;
        }

        $packed = inet_pton($ip);

        if ($packed === false) {
            return $ip;
        }

        if (str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return $this->toRfc5952($packed);
    }

    /**
     * Pure-hex RFC 5952 form: inet_ntop() emits dotted notation for some addresses (::5102:458e → ::81.2.69.142),
     * which GLOBAL_RANGE rejects.
     *
     * @param string $packed 16 bytes
     * @psalm-pure
     */
    private function toRfc5952(string $packed): string
    {
        /** @var list<int> $hextets */
        $hextets = array_values((array) unpack('n8', $packed));

        $bestStart = -1;
        $bestLength = 0;
        for ($i = 0; $i < 8; $i++) {
            if ($hextets[$i] !== 0) {
                continue;
            }

            $start = $i;
            while ($i < 8 && $hextets[$i] === 0) {
                $i++;
            }

            if ($i - $start > $bestLength) {
                $bestStart = $start;
                $bestLength = $i - $start;
            }
        }

        $hex = static fn(array $parts): string => implode(':', array_map(dechex(...), $parts));

        if ($bestLength < 2) {
            return $hex($hextets);
        }

        return $hex(array_slice($hextets, 0, $bestStart)).'::'.$hex(array_slice($hextets, $bestStart + $bestLength));
    }

    /**
     * Checks if the ip is valid.
     *
     * @param string $ip
     *
     * @return bool
     * @psalm-pure
     */
    private function isValid(string $ip, int $flags = FILTER_FLAG_GLOBAL_RANGE): bool
    {
        // $flags is a parameter so Psalm does not fold the constant: its hardcoded value mismatches PHP 8.5 (RedundantFlag).
        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
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

        return $this->cacheAllows($ip !== null);
    }

    /** Whether the configured `cache` mode stores a lookup for an explicit (or implicit client) IP. */
    private function cacheAllows(bool $explicitIp): bool
    {
        return match ($this->config('cache', 'none')) {
            'all' => true,
            'some' => $explicitIp,
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
