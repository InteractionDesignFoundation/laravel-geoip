<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests;

use InteractionDesignFoundation\GeoIP\GeoIP;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
class TestCase extends \Orchestra\Testbench\TestCase
{
    /** @inheritDoc */
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function makeGeoIP(array $config = []): GeoIP
    {
        $config = array_merge($this->getConfig(), $config);

        return new GeoIP($config, $this->app['cache']);
    }

    protected function getConfig(): array
    {
        $config = include(__DIR__ . '/../config/geoip.php');

        $this->databaseCheck($config['services']['maxmind_database']['database_path']);

        return $config;
    }

    /** Check for a test database and make a copy of it if it does not exist.*/
    protected function databaseCheck(string $databaseFilepath): void
    {
        if (file_exists($databaseFilepath) === false) {
            @mkdir(dirname($databaseFilepath), 0755, true);
            copy(__DIR__ . '/../resources/geoip.mmdb', $databaseFilepath);
        }
    }
}
