<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use InteractionDesignFoundation\GeoIP\Exceptions\MissingConfigurationException;
use InteractionDesignFoundation\GeoIP\Location;
use InteractionDesignFoundation\GeoIP\Services\IP2Location;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IP2Location::class)]
final class IP2LocationTest extends TestCase
{
    #[Test]
    public function it_throws_when_api_key_is_missing(): void
    {
        $this->expectException(MissingConfigurationException::class);

        new IP2Location([]);
    }

    #[Test]
    public function it_boots_with_valid_config(): void
    {
        $service = new IP2Location(['key' => 'test-key']);

        $this->assertSame('test-key', $service->config('key'));
    }

    #[Test]
    public function it_returns_a_location_instance(): void
    {
        $service = $this->createServiceWithMockedClient(json_encode($this->sampleApiResponse()));

        $location = $service->locate('8.8.8.8');

        $this->assertInstanceOf(Location::class, $location);
        $this->assertSame('8.8.8.8', $location->ip);
        $this->assertSame('US', $location->iso_code);
        $this->assertSame('United States of America', $location->country);
        $this->assertSame('Mountain View', $location->city);
        $this->assertSame('California', $location->state_name);
        $this->assertSame('94043', $location->postal_code);
        $this->assertEqualsWithDelta(37.38605, $location->lat, PHP_FLOAT_EPSILON);
        $this->assertSame(-122.08385, $location->lon);
        $this->assertSame('-07:00', $location->timezone);
        $this->assertFalse($location->default);
    }

    #[Test]
    public function it_throws_on_curl_error(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request failed');

        $service = $this->createServiceWithMockedClient('', 'Connection timed out');

        $service->locate('8.8.8.8');
    }

    #[Test]
    public function it_throws_on_invalid_json_response(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected ip2location.io response');

        $service = $this->createServiceWithMockedClient('not-json');

        $service->locate('8.8.8.8');
    }

    #[Test]
    public function it_throws_on_api_error_response(): void
    {
        $errorResponse = (object) [
            'error' => (object) [
                'error_code' => 10001,
                'error_message' => 'Invalid API key.',
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('IP2Location.io API error: Invalid API key.');

        $service = $this->createServiceWithMockedClient(json_encode($errorResponse));

        $service->locate('8.8.8.8');
    }

    #[Test]
    public function it_handles_partial_response_gracefully(): void
    {
        $partialResponse = (object) [
            'country_code' => 'DE',
            'country_name' => 'Germany',
        ];

        $service = $this->createServiceWithMockedClient(json_encode($partialResponse));

        $location = $service->locate('1.2.3.4');

        $this->assertInstanceOf(Location::class, $location);
        $this->assertSame('1.2.3.4', $location->ip);
        $this->assertSame('DE', $location->iso_code);
        $this->assertSame('Germany', $location->country);
        $this->assertNull($location->city);
    }

    private function createServiceWithMockedClient(string $responseBody, ?string $error = null): IP2Location
    {
        $client = $this->createMock(HttpClient::class);
        $client->method('get')->willReturn([$responseBody, []]);
        $client->method('getErrors')->willReturn($error);

        $service = new IP2Location(['key' => 'test-key']);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setValue($service, $client);

        return $service;
    }

    private function sampleApiResponse(): object
    {
        return (object) [
            'ip' => '8.8.8.8',
            'country_code' => 'US',
            'country_name' => 'United States of America',
            'region_name' => 'California',
            'city_name' => 'Mountain View',
            'latitude' => 37.38605,
            'longitude' => -122.08385,
            'zip_code' => '94043',
            'time_zone' => '-07:00',
            'isp' => 'Google LLC',
            'domain' => 'google.com',
            'is_proxy' => false,
        ];
    }
}
