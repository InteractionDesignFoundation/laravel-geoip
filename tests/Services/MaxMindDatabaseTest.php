<?php

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use InteractionDesignFoundation\GeoIP\Contracts\LocationProvider;
use InteractionDesignFoundation\GeoIP\Tests\TestCase;

class MaxMindDatabaseTest extends TestCase
{
    /**
     * @test
     */
    public function shouldReturnValidLocation(): void
    {
        $service = $this->getService();

        $location = $service->locate('81.2.69.142');

        $this->assertEquals('81.2.69.142', $location->ip);
        $this->assertFalse($location->default);
    }

    /**
     * @test
     */
    public function shouldReturnInvalidLocation(): void
    {
        $service = $this->getService();

        try {
            $location = $service->locate('1.1.1.1');
            $this->assertFalse($location->default);
        }
        catch (\GeoIp2\Exception\AddressNotFoundException $e) {
            $this->assertEquals('The address 1.1.1.1 is not in the database.', $e->getMessage());
        }
    }

    protected function getService(): LocationProvider
    {
        $classString = config('geoip.services.maxmind_database.class');
        assert(is_string($classString));

        $service = new $classString(
            config('geoip.services.maxmind_database')
        );

        assert($service instanceof LocationProvider);

        return $service;
    }
}
