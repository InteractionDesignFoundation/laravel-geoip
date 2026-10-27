<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use GeoIp2\WebService\Client;
use InteractionDesignFoundation\GeoIP\Services\IPData;
use InteractionDesignFoundation\GeoIP\Services\MaxMindWebService;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;
use InteractionDesignFoundation\GeoIP\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(IPData::class)]
#[CoversClass(MaxMindWebService::class)]
final class TimeoutConfigTest extends TestCase
{
    #[Test]
    public function http_service_uses_global_timeouts(): void
    {
        $service = $this->makeGeoIP([
            'timeout' => 7,
            'connect_timeout' => 3,
            'service' => 'ipdata',
            'services' => ['ipdata' => ['class' => IPData::class, 'key' => 'k']],
        ])->getService();

        $this->assertSame(['timeout' => 7, 'connect_timeout' => 3], $this->httpClientTimeouts($service));
    }

    #[Test]
    public function per_service_timeouts_win_over_global(): void
    {
        $service = $this->makeGeoIP([
            'timeout' => 7,
            'connect_timeout' => 3,
            'service' => 'ipdata',
            'services' => ['ipdata' => ['class' => IPData::class, 'key' => 'k', 'timeout' => 9]],
        ])->getService();

        $this->assertSame(['timeout' => 9, 'connect_timeout' => 3], $this->httpClientTimeouts($service));
    }

    #[Test]
    public function maxmind_web_service_receives_global_timeouts(): void
    {
        $service = $this->makeGeoIP([
            'timeout' => 7,
            'connect_timeout' => 3,
            'service' => 'maxmind_api',
            'services' => ['maxmind_api' => ['class' => MaxMindWebService::class, 'user_id' => 1, 'license_key' => 'k']],
        ])->getService();

        $this->assertSame([3.0, 7.0], $this->maxMindTimeouts($service));
    }

    #[Test]
    public function maxmind_web_service_per_service_timeouts_win_over_global(): void
    {
        $service = $this->makeGeoIP([
            'timeout' => 7,
            'connect_timeout' => 3,
            'service' => 'maxmind_api',
            'services' => ['maxmind_api' => ['class' => MaxMindWebService::class, 'user_id' => 1, 'license_key' => 'k', 'connect_timeout' => 1.5]],
        ])->getService();

        $this->assertSame([1.5, 7.0], $this->maxMindTimeouts($service));
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidTimeouts(): iterable
    {
        yield 'empty string' => [''];
        yield 'zero' => [0];
        yield 'non-numeric' => ['invalid'];
        yield 'negative' => [-1];
        yield 'numeric string overflowing to INF' => ['1e309'];
        yield 'finite value overflowing integer milliseconds' => [1e300];
    }

    #[Test]
    #[DataProvider('invalidTimeouts')]
    public function maxmind_web_service_falls_back_to_defaults_for_invalid_timeouts(mixed $invalid): void
    {
        $service = $this->makeGeoIP([
            'service' => 'maxmind_api',
            'services' => ['maxmind_api' => ['class' => MaxMindWebService::class, 'user_id' => 1, 'license_key' => 'k', 'timeout' => $invalid, 'connect_timeout' => $invalid]],
        ])->getService();

        $this->assertSame([2.0, 5.0], $this->maxMindTimeouts($service));
    }

    /** @return array{timeout: mixed, connect_timeout: mixed} */
    private function httpClientTimeouts(object $service): array
    {
        $client = new \ReflectionProperty($service, 'client')->getValue($service);
        $this->assertInstanceOf(HttpClient::class, $client);
        /** @var array<string, mixed> $config */
        $config = new \ReflectionProperty($client, 'config')->getValue($client);

        return ['timeout' => $config['timeout'], 'connect_timeout' => $config['connect_timeout']];
    }

    /** @return array{float|null, float|null} [connectTimeout, timeout] */
    private function maxMindTimeouts(object $service): array
    {
        $geoIp2Client = new \ReflectionProperty($service, 'client')->getValue($service);
        $this->assertInstanceOf(Client::class, $geoIp2Client);
        $webServiceClient = new \ReflectionProperty($geoIp2Client, 'client')->getValue($geoIp2Client);
        $this->assertIsObject($webServiceClient);

        /** @var float|null $connectTimeout */
        $connectTimeout = new \ReflectionProperty($webServiceClient, 'connectTimeout')->getValue($webServiceClient);
        /** @var float|null $timeout */
        $timeout = new \ReflectionProperty($webServiceClient, 'timeout')->getValue($webServiceClient);

        return [$connectTimeout, $timeout];
    }
}
