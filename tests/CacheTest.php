<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests;

use Illuminate\Cache\CacheManager;
use InteractionDesignFoundation\GeoIP\Cache;
use InteractionDesignFoundation\GeoIP\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Cache::class)]
final class CacheTest extends TestCase
{
    #[Test]
    public function should_return_valid_location(): void
    {
        $cache = new Cache(app(CacheManager::class), [], 30);
        $originalLocation = new Location([
            'ip' => '81.2.69.142',
            'iso_code' => 'US',
            'lat' => 41.31,
            'lon' => -72.92,
        ]);

        $cache->set('81.2.69.142', $originalLocation);
        $uncachedLocation = $cache->get('81.2.69.142');

        $this->assertInstanceOf(Location::class, $uncachedLocation);
        $this->assertSame($uncachedLocation->ip, $originalLocation->ip);
        $this->assertFalse($uncachedLocation->default);
    }

    #[Test]
    public function should_fall_back_to_untagged_cache_when_driver_does_not_support_tags(): void
    {
        // Switch to file driver which does not support tagging
        config(['cache.default' => 'file']);
        $cacheManager = app(CacheManager::class);

        $this->assertFalse($cacheManager->supportsTags(), 'File cache driver should not support tags');

        // Tags are configured, but driver doesn't support them — should not throw
        $cache = new Cache($cacheManager, ['some-tag'], 30);

        $location = new Location([
            'ip' => '81.2.69.142',
            'iso_code' => 'US',
            'lat' => 41.31,
            'lon' => -72.92,
        ]);

        $cache->set('81.2.69.142', $location);
        $cachedLocation = $cache->get('81.2.69.142');

        $this->assertInstanceOf(Location::class, $cachedLocation);
        $this->assertSame('81.2.69.142', $cachedLocation->ip);
    }

    #[Test]
    public function it_flushes_empty_cache(): void
    {
        $cache = new Cache(app(CacheManager::class), [], 30);

        $flushResult = $cache->flush();

        $this->assertTrue($flushResult);
    }

    #[Test]
    public function it_flushes_non_empty_cache(): void
    {
        $cache = new Cache(app(CacheManager::class), [], 30);
        $cache->set('42', new Location());

        $flushResult = $cache->flush();

        $this->assertTrue($flushResult);
    }
}
