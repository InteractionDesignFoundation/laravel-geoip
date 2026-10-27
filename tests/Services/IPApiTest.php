<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use InteractionDesignFoundation\GeoIP\Services\IPApi;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IPApi::class)]
final class IPApiTest extends TestCase
{
    private string $continentFile = '';

    #[\Override]
    protected function setUp(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'geoip_continents_');
        assert(is_string($file));
        $this->continentFile = $file;
        file_put_contents($file, '{}');

        // Laravel turns PHP warnings into exceptions; mimic it so a warning cannot hide behind a passing test.
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });
    }

    #[\Override]
    protected function tearDown(): void
    {
        restore_error_handler();
        @unlink($this->continentFile);
    }

    #[Test]
    public function it_boots_without_continent_path_configured(): void
    {
        $service = new IPApi([]);

        $this->assertSame('Unknown', $this->locateWith($service, $this->successBody())->continent);
    }

    #[Test]
    public function it_boots_when_continent_file_is_not_valid_json(): void
    {
        file_put_contents($this->continentFile, 'not-json');

        $service = new IPApi(['continent_path' => $this->continentFile]);

        $this->assertSame('Unknown', $this->locateWith($service, $this->successBody())->continent);
    }

    #[Test]
    public function it_maps_continent_from_file(): void
    {
        file_put_contents($this->continentFile, json_encode(['US' => 'NA'], JSON_THROW_ON_ERROR));

        $service = new IPApi(['continent_path' => $this->continentFile]);

        $this->assertSame('NA', $this->locateWith($service, $this->successBody())->continent);
    }

    /**
     * @return iterable<string, array{string}>
     * @psalm-pure
     */
    public static function unexpectedBodies(): iterable
    {
        yield 'not json' => ['not-json'];
        yield 'json scalar' => ['"text"'];
        yield 'object without status' => ['{"foo":"bar"}'];
    }

    #[Test]
    #[DataProvider('unexpectedBodies')]
    public function it_throws_runtime_exception_on_unexpected_body(string $body): void
    {
        $service = new IPApi(['continent_path' => $this->continentFile]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected ip-api.com response');

        $this->locateWith($service, $body);
    }

    #[Test]
    public function it_throws_runtime_exception_when_failed_response_has_no_message(): void
    {
        $service = new IPApi(['continent_path' => $this->continentFile]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed ip-api.com response');

        $this->locateWith($service, '{"status":"fail"}');
    }

    #[Test]
    public function it_reports_message_of_failed_response(): void
    {
        $service = new IPApi(['continent_path' => $this->continentFile]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed ip-api.com response: invalid query');

        $this->locateWith($service, '{"status":"fail","message":"invalid query"}');
    }

    private function locateWith(IPApi $service, string $body): \InteractionDesignFoundation\GeoIP\Location
    {
        $client = $this->createStub(HttpClient::class);
        $client->method('get')->willReturn([$body, []]);
        $client->method('getErrors')->willReturn(null);

        (new \ReflectionProperty($service, 'client'))->setValue($service, $client);

        return $service->locate('8.8.8.8');
    }

    /** @psalm-pure */
    private function successBody(): string
    {
        return json_encode([
            'status' => 'success',
            'countryCode' => 'US',
            'country' => 'United States',
            'city' => 'Mountain View',
            'region' => 'CA',
            'regionName' => 'California',
            'zip' => '94043',
            'lat' => 37.4,
            'lon' => -122.1,
            'timezone' => 'America/Los_Angeles',
        ], JSON_THROW_ON_ERROR);
    }
}
