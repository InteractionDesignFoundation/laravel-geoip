<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Container\Container;
use InteractionDesignFoundation\GeoIP\Services\IPApi;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(IPApi::class)]
final class IPApiTest extends TestCase
{
    private string $continentFile = '';

    private ?Container $previousContainer = null;

    #[\Override]
    protected function setUp(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'geoip_continents_');
        assert(is_string($file));
        $this->continentFile = $file;
        $this->previousContainer = Container::getInstance();
        Container::setInstance(new Container());
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
        Container::setInstance($this->previousContainer);
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

    #[Test]
    public function it_warns_once_when_secure_without_key_uses_plain_http(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('HTTP-only'));

        $service = new IPApi(['secure' => true, 'key' => null]);
        $service->setLogger($logger);
        $service->setLogger($logger);

        $this->assertSame('http://ip-api.com/', $this->baseUri($service));
    }

    #[Test]
    public function it_does_not_warn_when_secure_is_explicitly_disabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $service = new IPApi(['secure' => false, 'key' => '']);
        $service->setLogger($logger);

        $this->assertSame('http://ip-api.com/', $this->baseUri($service));
    }

    #[Test]
    public function it_uses_https_pro_endpoint_without_warning_when_key_is_set(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $service = new IPApi(['secure' => true, 'key' => 'k']);
        $service->setLogger($logger);

        $this->assertSame('https://pro.ip-api.com/', $this->baseUri($service));
    }

    #[Test]
    public function it_uses_https_pro_endpoint_when_key_is_set_even_if_secure_is_disabled(): void
    {
        $service = new IPApi(['secure' => false, 'key' => 'k']);

        $this->assertSame('https://pro.ip-api.com/', $this->baseUri($service));
    }

    #[Test]
    public function it_warns_at_most_once_across_instances_when_a_cache_is_available(): void
    {
        $this->bindCache(new Repository(new ArrayStore()));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('HTTP-only'));

        foreach (range(1, 3) as $_) {
            (new IPApi(['secure' => true, 'key' => null]))->setLogger($logger);
        }
    }

    #[Test]
    public function it_warns_for_every_instance_without_a_cache(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('warning');

        foreach (range(1, 2) as $_) {
            (new IPApi(['secure' => true, 'key' => null]))->setLogger($logger);
        }
    }

    #[Test]
    public function it_still_warns_when_the_cache_throws(): void
    {
        $cache = $this->createStub(Repository::class);
        $cache->method('add')->willThrowException(new \RuntimeException('cache down'));
        $this->bindCache($cache);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        (new IPApi(['secure' => true, 'key' => null]))->setLogger($logger);
    }

    private function bindCache(Repository $repository): void
    {
        $factory = $this->createStub(\Illuminate\Contracts\Cache\Factory::class);
        $factory->method('store')->willReturn($repository);
        Container::getInstance()->instance('cache', $factory);
    }

    private function baseUri(IPApi $service): string
    {
        $client = (new \ReflectionProperty($service, 'client'))->getValue($service);
        $this->assertInstanceOf(HttpClient::class, $client);
        /** @var array{base_uri: string} $config */
        $config = (new \ReflectionProperty($client, 'config'))->getValue($client);

        return $config['base_uri'];
    }

    private function locateWith(IPApi $service, string $body): \InteractionDesignFoundation\GeoIP\Location
    {
        $client = $this->createStub(HttpClient::class);
        $client->method('get')->willReturn([$body, []]);
        $client->method('getErrors')->willReturn(null);
        $client->method('getHttpCode')->willReturn(200);

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
