<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use InteractionDesignFoundation\GeoIP\Services\AbstractService;
use InteractionDesignFoundation\GeoIP\Services\IPApi;
use InteractionDesignFoundation\GeoIP\Services\IPData;
use InteractionDesignFoundation\GeoIP\Services\IPFinder;
use InteractionDesignFoundation\GeoIP\Services\IPGeoLocation;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Builds the real request URL of each HTTP service and stops before cURL runs. */
#[CoversClass(IPApi::class)]
#[CoversClass(IPData::class)]
#[CoversClass(IPFinder::class)]
#[CoversClass(IPGeoLocation::class)]
#[CoversClass(HttpClient::class)]
final class IPv6UrlTest extends TestCase
{
    /** @return iterable<string, array{class-string<AbstractService>, array<string, mixed>, string, string}> */
    public static function requests(): iterable
    {
        yield 'ipdata IPv4' => [IPData::class, ['key' => 'K'], '8.8.8.8', 'https://api.ipdata.co/8.8.8.8?api-key=K'];
        yield 'ipdata IPv6' => [IPData::class, ['key' => 'K'], '2a00:1450:4001::1', 'https://api.ipdata.co/2a00:1450:4001::1?api-key=K'];
        yield 'ipfinder IPv4' => [IPFinder::class, ['key' => 'K'], '8.8.8.8', 'https://api.ipfinder.io/v1/8.8.8.8?token=K'];
        yield 'ipfinder IPv6' => [IPFinder::class, ['key' => 'K'], '2a00:1450:4001::1', 'https://api.ipfinder.io/v1/2a00:1450:4001::1?token=K'];
        yield 'ip-api IPv4' => [IPApi::class, [], '8.8.8.8', 'http://ip-api.com/json/8.8.8.8?fields=49663&lang%5B0%5D=en'];
        yield 'ip-api IPv6' => [IPApi::class, [], '2a00:1450:4001::1', 'http://ip-api.com/json/2a00:1450:4001::1?fields=49663&lang%5B0%5D=en'];
        yield 'ipgeolocation IPv4' => [IPGeoLocation::class, ['key' => 'K'], '8.8.8.8', 'https://api.ipgeolocation.io/ipgeo?apiKey=K&ip=8.8.8.8'];
        yield 'ipgeolocation IPv6' => [IPGeoLocation::class, ['key' => 'K'], '2a00:1450:4001::1', 'https://api.ipgeolocation.io/ipgeo?apiKey=K&ip=2a00:1450:4001::1'];
    }

    /**
     * @param class-string<AbstractService> $class
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('requests')]
    public function it_requests_the_expected_url(string $class, array $config, string $ip, string $expectedUrl): void
    {
        $service = new \ReflectionClass($class)->newInstance($config);

        $clientProperty = new \ReflectionProperty($service, 'client');
        $client = $clientProperty->getValue($service);
        $this->assertInstanceOf(HttpClient::class, $client);
        /** @var array<string, mixed> $clientConfig */
        $clientConfig = new \ReflectionProperty($client, 'config')->getValue($client);

        $clientProperty->setValue($service, new class ($clientConfig) extends HttpClient {
            /** @return array<int, mixed> */
            #[\Override]
            protected function curlOptions(string $url, array $headers): array
            {
                throw new \RuntimeException($url);
            }
        });

        try {
            $service->locate($ip);
            $this->fail('Expected the request to be intercepted');
        } catch (\RuntimeException $runtimeException) {
            $this->assertSame($expectedUrl, $runtimeException->getMessage());
        }
    }
}
