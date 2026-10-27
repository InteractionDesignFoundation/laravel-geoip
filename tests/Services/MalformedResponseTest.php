<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use Closure;
use InteractionDesignFoundation\GeoIP\Services\AbstractService;
use InteractionDesignFoundation\GeoIP\Services\IPData;
use InteractionDesignFoundation\GeoIP\Services\IPFinder;
use InteractionDesignFoundation\GeoIP\Services\IPGeoLocation;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IPData::class)]
#[CoversClass(IPFinder::class)]
#[CoversClass(IPGeoLocation::class)]
final class MalformedResponseTest extends TestCase
{
    /** @return iterable<string, array{Closure(): AbstractService, string}> */
    public static function malformedBodies(): iterable
    {
        $services = [
            'IPData' => static fn(): AbstractService => new IPData(['key' => 'test-key']),
            'IPFinder' => static fn(): AbstractService => new IPFinder(['key' => 'test-key']),
            'IPGeoLocation' => static fn(): AbstractService => new IPGeoLocation(['key' => 'test-key']),
        ];

        foreach ($services as $name => $factory) {
            yield $name.' not json' => [$factory, 'not-json'];
            yield $name.' json scalar' => [$factory, '"text"'];
        }
    }

    /**
     * GeoIP::getLocation() only falls back to the default location for \Exception, so a malformed
     * body must not surface as a \TypeError or as a Location filled with nulls.
     *
     * @param Closure(): AbstractService $serviceFactory
     */
    #[Test]
    #[DataProvider('malformedBodies')]
    public function it_throws_exception_for_malformed_json_body(Closure $serviceFactory, string $body): void
    {
        $service = $serviceFactory();

        $client = $this->createStub(HttpClient::class);
        $client->method('get')->willReturn([$body, []]);
        $client->method('getErrors')->willReturn(null);
        $client->method('getHttpCode')->willReturn(200);
        (new \ReflectionProperty($service, 'client'))->setValue($service, $client);

        $this->expectException(\Exception::class);

        $service->locate('8.8.8.8');
    }
}
