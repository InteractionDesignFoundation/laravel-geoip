<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests;

use GeoIp2\Exception\AddressNotFoundException;
use Illuminate\Cache\CacheManager;
use InteractionDesignFoundation\GeoIP\Contracts\ServiceInterface;
use InteractionDesignFoundation\GeoIP\GeoIP;
use InteractionDesignFoundation\GeoIP\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type LocationArray from \InteractionDesignFoundation\GeoIP\Location
 */
#[CoversClass(GeoIP::class)]
final class GeoIPTest extends TestCase
{
    #[Test]
    public function should_get_usd_currency(): void
    {
        $geoIp = $this->makeGeoIP(['include_currency' => true]);

        $currency = $geoIp->getCurrency('US');

        $this->assertSame('USD', $currency);
    }

    #[Test]
    public function it_should_not_get_usd_currency(): void
    {
        $geoIp = $this->makeGeoIP(['include_currency' => true]);

        $currency = $geoIp->getCurrency('ZZ');

        $this->assertNull($currency);
    }

    #[Test]
    public function it_should_not_get_currency_when_it_is_disabled_by_config(): void
    {
        $geoIp = $this->makeGeoIP(['include_currency' => false]);

        $currency = $geoIp->getCurrency('ZZ');

        $this->assertNull($currency);
    }

    #[Test]
    public function get_service_returns_service_instance(): void
    {
        $geoIp = $this->makeGeoIP([
            'service' => 'maxmind_database',
        ]);

        $this->assertInstanceOf(\InteractionDesignFoundation\GeoIP\Contracts\ServiceInterface::class, $geoIp->getService());
    }

    #[Test]
    public function get_service_throws_when_class_is_not_configured(): void
    {
        $geoIp = $this->makeGeoIP([
            'service' => 'missing_class',
            'services' => [
                'missing_class' => [
                    'key' => 'value',
                ],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No GeoIP service is configured.');

        $geoIp->getService();
    }

    #[Test]
    public function get_service_throws_when_class_does_not_implement_service_interface(): void
    {
        $geoIp = $this->makeGeoIP([
            'service' => 'invalid_service',
            'services' => [
                'invalid_service' => [
                    'class' => \stdClass::class,
                ],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        $geoIp->getService();
    }

    #[Test]
    public function get_cache_returns_cache_instance(): void
    {
        $geoIp = $this->makeGeoIP();

        $this->assertInstanceOf(\InteractionDesignFoundation\GeoIP\Cache::class, $geoIp->getCache());
    }

    #[Test]
    public function get_client_ip_returns_request_ip(): void
    {
        request()->server->set('REMOTE_ADDR', '8.8.8.8');

        $geoIp = $this->makeGeoIP();

        $this->assertSame('8.8.8.8', $geoIp->getClientIP());
    }

    #[Test]
    public function get_client_ip_returns_fallback_when_request_ip_is_null(): void
    {
        request()->server->remove('REMOTE_ADDR');

        $geoIp = $this->makeGeoIP();

        $this->assertSame('127.0.0.0', $geoIp->getClientIP());
    }

    #[Test]
    public function it_does_not_treat_zero_string_as_missing_ip(): void
    {
        request()->server->set('REMOTE_ADDR', '8.8.8.8');

        $service = $this->createMock(ServiceInterface::class);
        $service->expects($this->never())->method('locate');
        $service->method('hydrate')
            ->willReturnCallback($this->hydrate(...));

        $geoIp = $this->makeGeoIP(['cache' => 'none']);
        $this->setService($geoIp, $service);

        $this->expectException(\InvalidArgumentException::class);

        $geoIp->getLocation('0');
    }

    #[Test]
    public function it_logs_error_when_service_lookup_fails_and_log_failures_is_enabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'GeoIP lookup failed',
                $this->callback(function (array $context): bool {
                    $this->assertArrayHasKey('exception', $context);
                    $this->assertInstanceOf(\Exception::class, $context['exception']);
                    return true;
                }),
            );

        $service = $this->createFailingService();

        $geoIp = $this->makeGeoIPWithLogger(['log_failures' => true, 'cache' => 'none'], $logger);
        $this->setService($geoIp, $service);

        $geoIp->getLocation('8.8.8.8');
    }

    #[Test]
    public function it_does_not_log_when_service_lookup_fails_and_log_failures_is_disabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())
            ->method('error');

        $service = $this->createFailingService();

        $geoIp = $this->makeGeoIPWithLogger(['log_failures' => false, 'cache' => 'none'], $logger);
        $this->setService($geoIp, $service);

        $geoIp->getLocation('8.8.8.8');
    }

    /**
     * @return iterable<string, array{string}>
     * @psalm-pure
     */
    public static function reservedIpProvider(): iterable
    {
        yield 'IPv6 loopback' => ['::1'];
        yield 'IPv6 link-local' => ['fe80::1'];
        yield 'IPv4-mapped IPv6 loopback' => ['::ffff:127.0.0.1'];
        yield 'IPv4 loopback' => ['127.0.0.1'];
        yield 'IPv4 private' => ['10.0.0.1'];
        yield 'IPv4 shared address space (CGNAT)' => ['100.64.0.1'];
        yield 'IPv6 documentation' => ['2001:db8::1'];
        yield '6to4 is not unwrapped' => ['2002:808:808::1'];
    }

    #[Test]
    #[DataProvider('reservedIpProvider')]
    public function it_returns_default_location_without_lookup_for_reserved_ips(string $ip): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $service = $this->createMock(ServiceInterface::class);
        $service->expects($this->never())->method('locate');
        $service->method('hydrate')
            ->willReturnCallback($this->hydrate(...));

        $geoIp = $this->makeGeoIPWithLogger(['cache' => 'none'], $logger);
        $this->setService($geoIp, $service);

        $this->assertTrue($geoIp->getLocation($ip)->default);
    }

    #[Test]
    public function it_returns_default_location_without_logging_when_address_is_not_in_database(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $service = $this->createStub(ServiceInterface::class);
        $service->method('locate')
            ->willThrowException(new AddressNotFoundException('The address 8.8.8.8 is not in the database.'));
        $service->method('hydrate')
            ->willReturnCallback($this->hydrate(...));

        $geoIp = $this->makeGeoIPWithLogger(['log_failures' => true, 'cache' => 'none'], $logger);
        $this->setService($geoIp, $service);

        $this->assertTrue($geoIp->getLocation('8.8.8.8')->default);
    }

    #[Test]
    #[DataProvider('invalidExplicitIpProvider')]
    public function it_throws_for_explicit_ip_that_is_not_an_ip_address(string $ip): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $service = $this->createMock(ServiceInterface::class);
        $service->expects($this->never())->method('locate');

        $geoIp = $this->makeGeoIPWithLogger(['cache' => 'none'], $logger);
        $this->setService($geoIp, $service);

        $this->expectException(\InvalidArgumentException::class);

        $geoIp->getLocation($ip);
    }

    /**
     * @return iterable<string, array{string}>
     * @psalm-pure
     */
    public static function invalidExplicitIpProvider(): iterable
    {
        yield 'absolute url' => ['http://evil.example/'];
        yield 'path injection' => ['1.1.1.1/../x?'];
        yield 'hostname' => ['example.com'];
    }

    #[Test]
    public function it_returns_default_location_for_private_explicit_ip(): void
    {
        $service = $this->createMock(ServiceInterface::class);
        $service->expects($this->never())->method('locate');
        $service->method('hydrate')
            ->willReturnCallback($this->hydrate(...));

        $geoIp = $this->makeGeoIP(['cache' => 'none']);
        $this->setService($geoIp, $service);

        $this->assertTrue($geoIp->getLocation('192.168.1.1')->default);
    }

    #[Test]
    public function it_returns_default_location_without_throwing_for_invalid_client_ip(): void
    {
        request()->server->set('REMOTE_ADDR', 'garbage');

        $service = $this->createMock(ServiceInterface::class);
        $service->expects($this->never())->method('locate');
        $service->method('hydrate')
            ->willReturnCallback($this->hydrate(...));

        $geoIp = $this->makeGeoIP(['cache' => 'none']);
        $this->setService($geoIp, $service);

        $this->assertTrue($geoIp->getLocation(null)->default);
    }

    #[Test]
    public function it_logs_not_found_addresses_at_debug_level_when_log_failures_is_enabled(): void
    {
        $service = $this->createStub(ServiceInterface::class);
        $service->method('locate')->willThrowException(new AddressNotFoundException('not found'));
        $service->method('hydrate')->willReturnCallback($this->hydrate(...));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        $logger->expects($this->once())
            ->method('debug')
            ->with('GeoIP address not found', ['ip' => '8.8.8.8', 'service' => $service::class]);

        $geoIp = $this->makeGeoIPWithLogger(['log_failures' => true, 'cache' => 'none'], $logger);
        $this->setService($geoIp, $service);

        $geoIp->getLocation('8.8.8.8');
    }

    #[Test]
    public function it_does_not_log_not_found_addresses_when_log_failures_is_disabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('debug');
        $logger->expects($this->never())->method('error');

        $service = $this->createStub(ServiceInterface::class);
        $service->method('locate')->willThrowException(new AddressNotFoundException('not found'));
        $service->method('hydrate')->willReturnCallback($this->hydrate(...));

        $geoIp = $this->makeGeoIPWithLogger(['log_failures' => false, 'cache' => 'none'], $logger);
        $this->setService($geoIp, $service);

        $geoIp->getLocation('8.8.8.8');
    }

    #[Test]
    public function it_caches_not_found_addresses_as_cached_default_locations(): void
    {
        $service = $this->createMock(ServiceInterface::class);
        $service->expects($this->once())
            ->method('locate')
            ->willThrowException(new AddressNotFoundException('not found'));
        $service->method('hydrate')->willReturnCallback($this->hydrate(...));

        $geoIp = $this->makeGeoIP(['cache' => 'all']);
        $this->setService($geoIp, $service);

        $first = $geoIp->getLocation('8.8.8.8');
        $second = $geoIp->getLocation('8.8.8.8');

        $this->assertTrue($first->default);
        $this->assertFalse($first->cached);
        $this->assertTrue($second->default);
        $this->assertTrue($second->cached);
        $this->assertSame('8.8.8.8', $second->ip, "The cached default must not carry the first caller's client IP");
    }

    #[Test]
    public function it_expires_cached_not_found_addresses_after_cache_not_found_expires(): void
    {
        $service = $this->createMock(ServiceInterface::class);
        $service->expects($this->exactly(2))
            ->method('locate')
            ->willThrowException(new AddressNotFoundException('not found'));
        $service->method('hydrate')->willReturnCallback($this->hydrate(...));

        $geoIp = $this->makeGeoIP(['cache' => 'all', 'cache_expires' => 3600, 'cache_not_found_expires' => 10]);
        $this->setService($geoIp, $service);

        $geoIp->getLocation('8.8.8.8');

        $hit = $geoIp->getLocation('8.8.8.8');
        $this->assertTrue($hit->cached);
        $this->assertTrue($hit->default);

        $this->travel(11)->seconds();
        $this->assertFalse($geoIp->getLocation('8.8.8.8')->cached);
    }

    #[Test]
    public function it_retries_transient_failures_on_next_call(): void
    {
        $service = $this->createMock(ServiceInterface::class);
        $service->expects($this->exactly(2))
            ->method('locate')
            ->willThrowException(new \Exception('timeout'));
        $service->method('hydrate')->willReturnCallback($this->hydrate(...));

        $geoIp = $this->makeGeoIPWithLogger(['cache' => 'all'], $this->createStub(LoggerInterface::class));
        $this->setService($geoIp, $service);

        $geoIp->getLocation('8.8.8.8');
        $this->assertFalse($geoIp->getLocation('8.8.8.8')->cached);
    }

    #[Test]
    public function it_looks_up_not_found_addresses_every_time_when_cache_is_none(): void
    {
        $service = $this->createMock(ServiceInterface::class);
        $service->expects($this->exactly(2))
            ->method('locate')
            ->willThrowException(new AddressNotFoundException('not found'));
        $service->method('hydrate')->willReturnCallback($this->hydrate(...));

        $geoIp = $this->makeGeoIP(['cache' => 'none']);
        $this->setService($geoIp, $service);

        $geoIp->getLocation('8.8.8.8');
        $geoIp->getLocation('8.8.8.8');
    }

    #[Test]
    public function it_does_not_cache_not_found_for_implicit_ip_when_cache_is_some(): void
    {
        $service = $this->createMock(ServiceInterface::class);
        $service->expects($this->exactly(2))
            ->method('locate')
            ->willThrowException(new AddressNotFoundException('not found'));
        $service->method('hydrate')->willReturnCallback($this->hydrate(...));

        request()->server->set('REMOTE_ADDR', '8.8.8.8');
        $geoIp = $this->makeGeoIP(['cache' => 'some']);
        $this->setService($geoIp, $service);

        $geoIp->getLocation();
        $geoIp->getLocation();
    }

    /**
     * @return iterable<string, array{string}>
     * @psalm-pure
     */
    public static function globalIpProvider(): iterable
    {
        yield 'IPv4' => ['8.8.8.8'];
        yield 'IPv6' => ['2a00:1450:4001::1'];
        yield 'NAT64 well-known prefix' => ['64:ff9b::808:808'];
    }

    #[Test]
    #[DataProvider('globalIpProvider')]
    public function it_looks_up_globally_routable_addresses(string $ip): void
    {
        $service = $this->createMock(ServiceInterface::class);
        $service->expects($this->once())
            ->method('locate')
            ->with($ip)
            ->willReturn(new Location(['ip' => $ip, 'iso_code' => 'IE']));

        $geoIp = $this->makeGeoIP(['cache' => 'none']);
        $this->setService($geoIp, $service);

        $this->assertFalse($geoIp->getLocation($ip)->default);
    }

    /**
     * @return iterable<string, array{string}>
     * @psalm-pure
     */
    public static function publicMappedIpProvider(): iterable
    {
        yield 'dotted' => ['::ffff:8.8.8.8'];
        yield 'hex' => ['::ffff:808:808'];
        yield 'hex zero-padded' => ['::ffff:0808:0808'];
    }

    #[Test]
    #[DataProvider('publicMappedIpProvider')]
    public function it_looks_up_public_ipv4_mapped_addresses_as_ipv4(string $ip): void
    {
        $service = $this->createMock(ServiceInterface::class);
        $service->expects($this->once())
            ->method('locate')
            ->with('8.8.8.8')
            ->willReturn(new Location(['ip' => '8.8.8.8', 'iso_code' => 'US']));

        $geoIp = $this->makeGeoIP(['cache' => 'all']);
        $this->setService($geoIp, $service);

        $this->assertFalse($geoIp->getLocation($ip)->default);
        $this->assertTrue($geoIp->getLocation('8.8.8.8')->cached);

        $geoIp->getCache()->flush();
    }

    private function makeGeoIPWithLogger(array $config, LoggerInterface $logger): GeoIP
    {
        $config = array_merge($this->getConfig(), $config);

        return new GeoIP($config, app(CacheManager::class), $logger);
    }

    #[Test]
    public function it_handles_null_iso_code_when_resolving_currency(): void
    {
        $locationWithNullIsoCode = new Location([
            'ip' => '8.8.8.8',
            'iso_code' => null,
            'country' => null,
            'city' => null,
            'state' => null,
            'state_name' => null,
            'postal_code' => null,
            'lat' => null,
            'lon' => null,
            'timezone' => null,
            'continent' => null,
        ]);

        $service = $this->createStub(ServiceInterface::class);
        $service->method('locate')->willReturn($locationWithNullIsoCode);
        $service->method('hydrate')
            ->willReturnCallback($this->hydrate(...));

        $geoIp = $this->makeGeoIP(['include_currency' => true, 'cache' => 'none']);
        $this->setService($geoIp, $service);

        $location = $geoIp->getLocation('8.8.8.8');

        $this->assertNull($location->currency);
        $this->assertNull($location->iso_code);
    }

    /**
     * @param LocationArray|array{} $attributes
     * @psalm-impure
     */
    private function hydrate(array $attributes): Location
    {
        return new Location($attributes);
    }

    private function createFailingService(): ServiceInterface
    {
        $service = $this->createStub(ServiceInterface::class);
        $service->method('locate')
            ->willThrowException(new \Exception('Service lookup failed'));
        $service->method('hydrate')
            ->willReturnCallback($this->hydrate(...));

        return $service;
    }

    private function setService(GeoIP $geoIp, ServiceInterface $service): void
    {
        $reflection = new \ReflectionProperty($geoIp, 'service');
        $reflection->setValue($geoIp, $service);
    }
}
