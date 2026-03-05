<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests;

use InteractionDesignFoundation\GeoIP\GeoIP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GeoIP::class)]
final class ShouldCacheTest extends TestCase
{
    private const VALID_IP = '81.2.69.142';

    #[Test]
    public function cache_mode_all_caches_location_with_explicit_ip(): void
    {
        $geoIp = $this->makeGeoIP([
            'cache' => 'all',
            'service' => 'maxmind_database',
        ]);

        $geoIp->getLocation(self::VALID_IP);

        $cached = $geoIp->getCache()->get(self::VALID_IP);
        $this->assertInstanceOf(\InteractionDesignFoundation\GeoIP\Location::class, $cached, 'Location should be cached when cache mode is "all" with explicit IP');
    }

    #[Test]
    public function cache_mode_some_caches_location_with_explicit_ip(): void
    {
        $geoIp = $this->makeGeoIP([
            'cache' => 'some',
            'service' => 'maxmind_database',
        ]);

        $geoIp->getLocation(self::VALID_IP);

        $cached = $geoIp->getCache()->get(self::VALID_IP);
        $this->assertInstanceOf(\InteractionDesignFoundation\GeoIP\Location::class, $cached, 'Location should be cached when cache mode is "some" with explicit IP');
    }

    #[Test]
    public function cache_mode_some_does_not_cache_without_explicit_ip(): void
    {
        $geoIp = $this->makeGeoIP([
            'cache' => 'some',
            'service' => 'maxmind_database',
        ]);

        // Calling without an IP triggers auto-detection (resolves to 127.0.0.0 in tests),
        // which produces a default location. The shouldCache method receives $ip = null.
        $location = $geoIp->getLocation();

        $this->assertTrue($location->default, 'Location should be default when no valid IP is detected');
        // Default locations are never cached (checked before the match expression),
        // and additionally $ip is null so "some" would return false.
        $clientIp = $geoIp->getClientIP();
        $cached = $geoIp->getCache()->get($clientIp);
        $this->assertNotInstanceOf(\InteractionDesignFoundation\GeoIP\Location::class, $cached, 'Location should not be cached when cache mode is "some" without explicit IP');
    }

    #[Test]
    public function cache_mode_none_does_not_cache_location(): void
    {
        $geoIp = $this->makeGeoIP([
            'cache' => 'none',
            'service' => 'maxmind_database',
        ]);

        $geoIp->getLocation(self::VALID_IP);

        $cached = $geoIp->getCache()->get(self::VALID_IP);
        $this->assertNotInstanceOf(\InteractionDesignFoundation\GeoIP\Location::class, $cached, 'Location should not be cached when cache mode is "none"');
    }

    #[Test]
    public function default_location_is_never_cached_regardless_of_cache_mode(): void
    {
        $geoIp = $this->makeGeoIP([
            'cache' => 'all',
            'service' => 'maxmind_database',
        ]);

        // 127.0.0.0 is a private IP, so the service returns the default location
        $location = $geoIp->getLocation('127.0.0.0');

        $this->assertTrue($location->default, 'Location should be marked as default for private IP');
        $cached = $geoIp->getCache()->get('127.0.0.0');
        $this->assertNotInstanceOf(\InteractionDesignFoundation\GeoIP\Location::class, $cached, 'Default location should never be cached');
    }

    #[Test]
    public function already_cached_location_is_not_re_cached(): void
    {
        $geoIp = $this->makeGeoIP([
            'cache' => 'all',
            'service' => 'maxmind_database',
        ]);

        // First call: caches the location
        $firstLocation = $geoIp->getLocation(self::VALID_IP);
        $this->assertNotTrue($firstLocation->cached, 'First call should not be from cache');

        // Second call: should retrieve from cache, not re-cache
        $secondLocation = $geoIp->getLocation(self::VALID_IP);
        $this->assertTrue($secondLocation->cached, 'Second call should be from cache');

        // Verify the cached flag prevents re-caching by confirming
        // the second call came from cache (cached=true means shouldCache returns false)
        $this->assertSame($firstLocation->ip, $secondLocation->ip);
    }
}
