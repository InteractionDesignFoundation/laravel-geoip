<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests;

use InteractionDesignFoundation\GeoIP\GeoIP;
use InteractionDesignFoundation\GeoIP\Location;

/**
 * @covers \InteractionDesignFoundation\GeoIP\GeoIP
 */
class GeoIPTest extends TestCase
{
    /** @test */
    public function should_get_usd_currency(): void
    {
        $geoIp = $this->makeGeoIP();

        $this->assertSame($geoIp->getCurrency('US'), 'USD');
    }

    /** @test */
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

    /** @test */
    public function get_cache_returns_cache_instance(): void
    {
        $geoIp = $this->makeGeoIP();

        $this->assertInstanceOf(\InteractionDesignFoundation\GeoIP\Cache::class, $geoIp->getCache());
    }

    /** @test */
    public function it_gets_default_location_using_resolver_if_it_is_specified(): void
    {
        GeoIP::resolveDefaultLocationUsing(static function (): Location {
            return new Location([
                'ip' => '192.168.0.42',
                'iso_code' => 'CA',
            ]);
        });
        $geoIp = $this->makeGeoIP();

        $defaultLocation = $geoIp->getLocation();

        $this->assertSame($defaultLocation['ip'], '192.168.0.42');
        $this->assertSame($defaultLocation['iso_code'], 'CA');
    }
}
