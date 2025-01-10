<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests;

use Illuminate\Cache\CacheManager;
use InteractionDesignFoundation\GeoIP\Cache;
use InteractionDesignFoundation\GeoIP\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Cache::class)]
class CacheTest extends TestCase
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

        $cache->set($originalLocation['ip'], $originalLocation);
        $uncachedLocation = $cache->get($originalLocation['ip']);

        $this->assertInstanceOf(Location::class, $uncachedLocation);
        $this->assertSame($uncachedLocation->ip, $originalLocation->ip);
        $this->assertSame($uncachedLocation->default, false);
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
