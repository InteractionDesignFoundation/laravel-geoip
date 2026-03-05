<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests;

use InteractionDesignFoundation\GeoIP\GeoIP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GeoIP::class)]
class GeoIPTest extends TestCase
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
}
