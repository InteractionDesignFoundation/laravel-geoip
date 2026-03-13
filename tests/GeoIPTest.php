<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests;

use InteractionDesignFoundation\GeoIP\Contracts\ServiceInterface;
use InteractionDesignFoundation\GeoIP\GeoIP;
use InteractionDesignFoundation\GeoIP\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;

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

        // Get config values
        $config = $this->getConfig()['services']['maxmind_database'];
        unset($config['class']);

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
        $this->app['request']->server->set('REMOTE_ADDR', '8.8.8.8');

        $geoIp = $this->makeGeoIP();

        $this->assertSame('8.8.8.8', $geoIp->getClientIP());
    }

    #[Test]
    public function get_client_ip_returns_fallback_when_request_ip_is_null(): void
    {
        $this->app['request']->server->remove('REMOTE_ADDR');

        $geoIp = $this->makeGeoIP();

        $this->assertSame('127.0.0.0', $geoIp->getClientIP());
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

    private function makeGeoIPWithLogger(array $config, LoggerInterface $logger): GeoIP
    {
        $config = array_merge($this->getConfig(), $config);

        return new GeoIP($config, $this->app['cache'], $logger);
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
            ->willReturnCallback(static fn(array $attributes): Location => new Location($attributes));

        $geoIp = $this->makeGeoIP(['include_currency' => true, 'cache' => 'none']);
        $this->setService($geoIp, $service);

        $location = $geoIp->getLocation('8.8.8.8');

        $this->assertNull($location->currency);
        $this->assertNull($location->iso_code);
    }

    private function createFailingService(): ServiceInterface
    {
        $service = $this->createStub(ServiceInterface::class);
        $service->method('locate')
            ->willThrowException(new \Exception('Service lookup failed'));
        $service->method('hydrate')
            ->willReturnCallback(static fn(array $attributes): Location => new Location($attributes));

        return $service;
    }

    private function setService(GeoIP $geoIp, ServiceInterface $service): void
    {
        $reflection = new \ReflectionProperty($geoIp, 'service');
        $reflection->setValue($geoIp, $service);
    }
}
