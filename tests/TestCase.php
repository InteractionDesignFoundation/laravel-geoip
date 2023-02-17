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

        return new \InteractionDesignFoundation\GeoIP\GeoIP($config, $cacheMock);
    }

    protected function getConfig()
    {
        $config = include(__DIR__ . '/../config/geoip.php');

        $this->databaseCheck($config['services']['maxmind_database']['database_path']);

        return $config;
    }

    /**
     * Check for test database and make a copy of it
     * if it does not exist.
     *
     * @param string $database
     */
    protected function databaseCheck(string $database): void
    {
        if (file_exists($database) === false) {
            @mkdir(dirname($database), 0755, true);
            copy(__DIR__ . '/../resources/geoip.mmdb', $database);
        }
    }
}