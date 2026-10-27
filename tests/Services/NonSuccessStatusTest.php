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
final class NonSuccessStatusTest extends TestCase
{
    /** @return iterable<string, array{\Closure(): AbstractService, string, int, string}> */
    public static function nonSuccessResponses(): iterable
    {
        $services = [
            'IPApi' => [static fn(): AbstractService => new IPApi(['key' => 'secret-key']), 'ip-api.com'],
            'IPData' => [static fn(): AbstractService => new IPData(['key' => 'secret-key']), 'ipdata.co'],
            'IPFinder' => [static fn(): AbstractService => new IPFinder(['key' => 'secret-key']), 'ipfinder.io'],
            'IPGeoLocation' => [static fn(): AbstractService => new IPGeoLocation(['key' => 'secret-key']), 'ipgeolocation.io'],
            'IP2Location' => [static fn(): AbstractService => new IP2Location(['key' => 'secret-key']), 'ip2location.io'],
        ];

        foreach ($services as $name => [$factory, $provider]) {
            foreach ([301, 404, 429, 503] as $status) {
                yield $name.' '.$status => [$factory, $provider, $status, '{"status":"success","country_code":"US"}'];
                yield $name.' '.$status.' empty body' => [$factory, $provider, $status, ''];
            }
        }
    }

    /**
     * An error page must not be parsed as a lookup result, and the exception must not leak the API key.
     *
     * @param \Closure(): AbstractService $serviceFactory
     */
    #[Test]
    #[DataProvider('nonSuccessResponses')]
    public function it_throws_exception_naming_provider_and_status_on_non_2xx_response(\Closure $serviceFactory, string $provider, int $status, string $body): void
    {
        $service = $serviceFactory();

        $client = $this->createStub(HttpClient::class);
        $client->method('get')->willReturn([$body, []]);
        $client->method('getErrors')->willReturn(null);
        $client->method('getHttpCode')->willReturn($status);
        (new \ReflectionProperty($service, 'client'))->setValue($service, $client);

        $message = '';
        try {
            $service->locate('8.8.8.8');
        } catch (\RuntimeException $runtimeException) {
            $message = $runtimeException->getMessage();
        }

        $this->assertStringContainsString($provider, $message, 'Expected a RuntimeException for HTTP '.$status);
        $this->assertStringContainsString((string) $status, $message);
        $this->assertStringNotContainsString('secret-key', $message);
    }
}
