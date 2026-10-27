<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests;

use Illuminate\Cache\CacheManager;
use InteractionDesignFoundation\GeoIP\GeoIP;
use PHPUnit\Framework\Attributes\CoversNothing;
use Psr\Log\LoggerInterface;

#[CoversNothing]
class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function makeGeoIP(array $config = []): GeoIP
    {
        $config = array_merge($this->getConfig(), $config);

        return new GeoIP($config, app(CacheManager::class), app(LoggerInterface::class));
    }

    /** @return array{services: array{maxmind_database: array{class: class-string, database_path: string, ...}, ...}, ...} */
    protected function getConfig(): array
    {
        /** @var array{services: array{maxmind_database: array{class: class-string, database_path: string, ...}, ...}, ...} $config */
        $config = include(__DIR__.'/../config/geoip.php');

        $this->databaseCheck($config['services']['maxmind_database']['database_path']);

        return $config;
    }

    /** Check for a test database and make a copy of it if it does not exist.*/
    protected function databaseCheck(string $databaseFilepath): void
    {
        if (file_exists($databaseFilepath) === false) {
            @mkdir(dirname($databaseFilepath), 0o755, true);
            copy(__DIR__.'/../resources/geoip.mmdb', $databaseFilepath);
        }
    }
}
