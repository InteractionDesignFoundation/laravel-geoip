<?php

namespace InteractionDesignFoundation\GeoIP\Tests;

use InteractionDesignFoundation\GeoIP\GeoIP;
use InteractionDesignFoundation\GeoIP\GeoIPServiceProvider;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Cache\CacheManager;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            GeoIPServiceProvider::class
        ];
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    protected function makeGeoIP(MockInterface $cacheMock = null): GeoIP
    {
        $cacheMock = $cacheMock ?: Mockery::mock(CacheManager::class);

        $cacheMock->shouldReceive('tags')->with(['laravel-geoip-location'])->andReturnSelf();

        return new GeoIP($cacheMock);
    }

    /**
     * Check for test database and make a copy of it
     * if it does not exist.
     */
    protected function databaseCheck(string $database): void
    {
        if (file_exists($database) === false) {
            @mkdir(dirname($database), 0755, true);
            copy(__DIR__ . '/../resources/geoip.mmdb', $database);
        }
    }
}