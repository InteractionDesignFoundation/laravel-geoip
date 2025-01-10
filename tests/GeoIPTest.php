<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests;

use InteractionDesignFoundation\GeoIP\GeoIP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GeoIP::class)]
class GeoIPTest extends TestCase
{
    #[Test]
    public function should_get_usd_currency(): void
    {
        $geoIp = $this->makeGeoIP(['include_currency' => true]);

        $currency = $geoIp->getCurrency('US');

        $this->assertSame('USD', $currency);
    }

    #[Test]
    public function it_should_not_get_usd_currency(): void
    {
        $geoIp = $this->makeGeoIP(['include_currency' => true]);

        $currency = $geoIp->getCurrency('ZZ');

        $this->assertNull($currency);
    }

    #[Test]
    public function it_should_not_get_currency_when_it_is_disabled_by_config(): void
    {
        $geoIp = $this->makeGeoIP(['include_currency' => false]);

        $currency = $geoIp->getCurrency('ZZ');

        $this->assertNull($currency);
    }

    #[Test]
    public function get_service_returns_service_instance(): void
    {
        $geoIp = $this->makeGeoIP([
            'service' => 'maxmind_database',
        ]);

        // Get config values
        $config = $this->getConfig()['services']['maxmind_database'];
        unset($config['class']);

        $this->assertInstanceOf(\InteractionDesignFoundation\GeoIP\Contracts\ServiceInterface::class, $geoIp->getService());
    }

    #[Test]
    public function get_cache_returns_cache_instance(): void
    {
        $geoIp = $this->makeGeoIP();

        $this->assertInstanceOf(\InteractionDesignFoundation\GeoIP\Cache::class, $geoIp->getCache());
    }
}
