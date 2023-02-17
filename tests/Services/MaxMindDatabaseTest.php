<?php

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use InteractionDesignFoundation\GeoIP\LocationResponse;
use InteractionDesignFoundation\GeoIP\Tests\TestCase;

class MaxMindDatabaseTest extends TestCase
{
    /** @test */
    public function should_return_config_value(): void
    {
        [$service, $config] = $this->getService();

        $this->assertSame($config['database_path'], $service->config('database_path'));
    }

    /** @test */
    public function should_return_valid_location(): void
    {
        [$service, $config] = $this->getService();

        $location = $service->locate('81.2.69.142');

        $this->assertInstanceOf(\InteractionDesignFoundation\GeoIP\Location::class, $location);
        $this->assertSame('81.2.69.142', $location->ip);
        $this->assertFalse($location->default);
    }

    /** @test */
    public function it_can_return_a_location_response_object(): void
    {
        config()->set('geoip.should_use_dto_response', true);
        [$service, $config] = $this->getService();

        $location = $service->locate('81.2.69.142');

        $this->assertInstanceOf(LocationResponse::class, $location);
        $this->assertSame('81.2.69.142', $location->ip);
        $this->assertFalse($location->default);
    }

    /** @test */
    public function should_return_invalid_location(): void
    {
        [$service, $config] = $this->getService();

        try {
            $location = $service->locate('1.1.1.1');
            $this->assertFalse($location->default);
        }
        catch (\GeoIp2\Exception\AddressNotFoundException $e) {
            $this->assertSame('The address 1.1.1.1 is not in the database.', $e->getMessage());
        }
    }

    protected function getService(): array
    {
        $config = $this->getConfig()['services']['maxmind_database'];

        $service = new $config['class']($config);

        return [$service, $config];
    }
}
