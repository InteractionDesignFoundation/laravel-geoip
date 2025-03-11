<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use InteractionDesignFoundation\GeoIP\Services\MaxMindDatabase;
use InteractionDesignFoundation\GeoIP\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(MaxMindDatabase::class)]
class MaxMindDatabaseTest extends TestCase
{
    #[Test]
    public function should_return_config_value(): void
    {
        [$service, $config] = $this->getService();

        $this->assertSame($service->config('database_path'), $config['database_path']);
    }

    #[Test]
    public function should_return_valid_location(): void
    {
        [$service] = $this->getService();

        $location = $service->locate('81.2.69.142');

        $this->assertInstanceOf(\InteractionDesignFoundation\GeoIP\Location::class, $location);
        $this->assertSame('81.2.69.142', $location->ip);
        $this->assertFalse($location->default);
    }

    #[Test]
    public function should_return_invalid_location_for_special_addresses(): void
    {
        [$service] = $this->getService();

        try {
            $location = $service->locate('1.1.1.1');
            $this->assertFalse($location->default);
        } catch (\GeoIp2\Exception\AddressNotFoundException $addressNotFoundException) {
            $this->assertSame('The address 1.1.1.1 is not in the database.', $addressNotFoundException->getMessage());
        }
    }

    /** @return list{\InteractionDesignFoundation\GeoIP\Contracts\ServiceInterface, array<string, mixed>} */
    protected function getService(): array
    {
        $config = $this->getConfig()['services']['maxmind_database'];

        $service = new $config['class']($config);

        return [$service, $config];
    }
}
