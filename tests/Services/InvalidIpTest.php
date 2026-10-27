<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use InteractionDesignFoundation\GeoIP\Services\AbstractService;
use InteractionDesignFoundation\GeoIP\Services\IP2Location;
use InteractionDesignFoundation\GeoIP\Services\IPApi;
use InteractionDesignFoundation\GeoIP\Services\IPData;
use InteractionDesignFoundation\GeoIP\Services\IPFinder;
use InteractionDesignFoundation\GeoIP\Services\IPGeoLocation;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractService::class)]
final class InvalidIpTest extends TestCase
{
    /** @return iterable<string, array{AbstractService, string}> */
    public static function httpServicesWithInvalidIps(): iterable
    {
        $services = [
            'IPApi' => static fn(): AbstractService => new IPApi([]),
            'IPData' => static fn(): AbstractService => new IPData(['key' => 'k']),
            'IPFinder' => static fn(): AbstractService => new IPFinder(['key' => 'k']),
            'IPGeoLocation' => static fn(): AbstractService => new IPGeoLocation([]),
            'IP2Location' => static fn(): AbstractService => new IP2Location(['key' => 'k']),
        ];
        $invalidIps = [
            'absolute url' => 'http://evil.example/',
            'path traversal' => '1.1.1.1/../x?',
            'query injection' => '1.1.1.1?x=1',
            'empty' => '',
        ];

        foreach ($services as $serviceName => $factory) {
            foreach ($invalidIps as $label => $ip) {
                yield $serviceName.': '.$label => [$factory(), $ip];
            }
        }
    }

    #[Test]
    #[DataProvider('httpServicesWithInvalidIps')]
    public function it_rejects_non_ip_input_without_sending_a_request(AbstractService $service, string $ip): void
    {
        $client = $this->createMock(HttpClient::class);
        $client->expects($this->never())->method('get');
        $client->expects($this->never())->method('execute');
        (new \ReflectionProperty($service, 'client'))->setValue($service, $client);

        $this->expectException(\InvalidArgumentException::class);

        $service->locate($ip);
    }
}
