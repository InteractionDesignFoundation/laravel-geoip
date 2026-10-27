<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

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
    /** @return iterable<string, array{class-string<AbstractService>, string}> */
    public static function malformedBodies(): iterable
    {
        foreach ([IPData::class, IPFinder::class, IPGeoLocation::class] as $service) {
            yield $service.' not json' => [$service, 'not-json'];
            yield $service.' json scalar' => [$service, '"text"'];
        }
    }

    /**
     * GeoIP::getLocation() only falls back to the default location for \Exception, so a malformed
     * body must not surface as a \TypeError or as a Location filled with nulls.
     *
     * @param class-string<AbstractService> $serviceClass
     */
    #[Test]
    #[DataProvider('malformedBodies')]
    public function it_throws_exception_for_malformed_json_body(string $serviceClass, string $body): void
    {
        $service = new $serviceClass(['key' => 'test-key']);

        $client = $this->createStub(HttpClient::class);
        $client->method('get')->willReturn([$body, []]);
        $client->method('getErrors')->willReturn(null);
        (new \ReflectionProperty($service, 'client'))->setValue($service, $client);

        $this->expectException(\Exception::class);

        $service->locate('8.8.8.8');
    }
}
