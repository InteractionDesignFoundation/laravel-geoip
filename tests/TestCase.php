<?php

namespace InteractionDesignFoundation\GeoIP\Tests;

use InteractionDesignFoundation\GeoIP\GeoIP;
use InteractionDesignFoundation\GeoIP\GeoIPServiceProvider;
use Mockery;
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

    protected function makeGeoIP(array $config = [], $cacheMock = null): GeoIP
    {
        $cacheMock = $cacheMock ?: Mockery::mock(CacheManager::class);

        $config = array_merge($this->getConfig(), $config);

        $cacheMock->shouldReceive('tags')->with(['laravel-geoip-location'])->andReturnSelf();

        return new GeoIP($config, $cacheMock);
    }

    protected function getConfig(): array
    {
        $databasePath = config('geoip.services.maxmind_database.database_path');

        assert(is_string($databasePath));

        $this->databaseCheck($databasePath);

        return config('geoip');
    }

    /**
     * Check for test database and make a copy of it
     * if it does not exist.
     *
     * @param string $database
     */
    protected function databaseCheck($database)
    {
        if (file_exists($database) === false) {
            @mkdir(dirname($database), 0755, true);
            copy(__DIR__ . '/../resources/geoip.mmdb', $database);
        }
    }
}