<?php

namespace InteractionDesignFoundation\GeoIP\Tests;

class GeoIPTest extends TestCase
{
    /** @test */
    public function shouldGetUSDCurrency(): void
    {
        $geo_ip = $this->makeGeoIP();

        $this->assertEquals('USD', $geo_ip->getCurrency('US'));
    }

    /**
     * @test
     */
    public function testGetService(): void
    {
        $geo_ip = $this->makeGeoIP([
            'service' => 'maxmind_database',
        ]);

        $this->assertInstanceOf(\InteractionDesignFoundation\GeoIP\Contracts\LocationProvider::class, $geo_ip->getService());
    }

    /**
     * @test
     */
    public function testGetCache(): void
    {
        $geo_ip = $this->makeGeoIP();

        $this->assertInstanceOf(\InteractionDesignFoundation\GeoIP\Cache::class, $geo_ip->getCache());
    }
}