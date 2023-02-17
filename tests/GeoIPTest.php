<?php

namespace InteractionDesignFoundation\GeoIP\Tests;

use InteractionDesignFoundation\GeoIP\Contracts\IpLocationProvider;
use InteractionDesignFoundation\GeoIP\GeoIP;
use Mockery;

class GeoIPTest extends TestCase
{
    /** @test */
    public function should_get_usd_currency(): void
    {
        $geo_ip = $this->makeGeoIP();

        $this->assertSame('USD', $geo_ip->getCurrency('US'));
    }

    /** @test */
    public function helper_returns_a_geoip_instance(): void
    {
        $instance = geoip();

        $this->assertInstanceOf(GeoIP::class, $instance);
    }
}