<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Support;

use InteractionDesignFoundation\GeoIP\Support\HttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpClient::class)]
final class HttpClientTest extends TestCase
{
    #[Test]
    public function it_uses_short_default_timeouts(): void
    {
        $options = $this->curlOptions([]);

        $this->assertSame(2000, $options[\CURLOPT_CONNECTTIMEOUT_MS]);
        $this->assertSame(5000, $options[\CURLOPT_TIMEOUT_MS]);
    }

    #[Test]
    public function it_applies_configured_timeouts(): void
    {
        $options = $this->curlOptions(['connect_timeout' => 0.5, 'timeout' => '3']);

        $this->assertSame(500, $options[\CURLOPT_CONNECTTIMEOUT_MS]);
        $this->assertSame(3000, $options[\CURLOPT_TIMEOUT_MS]);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidTimeouts(): iterable
    {
        yield 'empty string' => [''];
        yield 'zero' => [0];
        yield 'non-numeric' => ['invalid'];
        yield 'negative' => [-1];
        yield 'null' => [null];
        yield 'INF' => [\INF];
        yield 'NAN' => [\NAN];
        yield 'numeric string overflowing to INF' => ['1e309'];
        yield 'finite value overflowing integer milliseconds' => [1e300];
        yield 'largest integer' => [\PHP_INT_MAX];
    }

    /** A 0 would mean "no deadline" to cURL, so an unusable value must not reach it. */
    #[Test]
    #[DataProvider('invalidTimeouts')]
    public function it_falls_back_to_defaults_for_invalid_timeouts(mixed $invalid): void
    {
        $options = $this->curlOptions(['connect_timeout' => $invalid, 'timeout' => $invalid]);

        $this->assertSame(2000, $options[\CURLOPT_CONNECTTIMEOUT_MS]);
        $this->assertSame(5000, $options[\CURLOPT_TIMEOUT_MS]);
    }

    #[Test]
    public function it_resolves_timeouts_to_finite_positive_seconds(): void
    {
        $this->assertEqualsWithDelta(0.25, HttpClient::resolveTimeout('0.25', 5.0), \PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(5.0, HttpClient::resolveTimeout(\INF, 5.0), \PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(5.0, HttpClient::resolveTimeout('1e309', 5.0), \PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(5.0, HttpClient::resolveTimeout(1e300, 5.0), \PHP_FLOAT_EPSILON);
    }

    /** @return iterable<string, array{string, string}> */
    public static function urls(): iterable
    {
        yield 'IPv4 path' => ['8.8.8.8?k=v', 'https://api.test/v1/8.8.8.8?k=v'];
        yield 'IPv6 path' => ['2a00:1450:4001::1?k=v', 'https://api.test/v1/2a00:1450:4001::1?k=v'];
        yield 'IPv6 path with letters only' => ['abcd::1?k=v', 'https://api.test/v1/abcd::1?k=v'];
        yield 'IPv6 loopback' => ['::1?k=v', 'https://api.test/v1/::1?k=v'];
        yield 'empty path' => ['', 'https://api.test/v1/'];
        yield 'absolute https' => ['https://other.test/x', 'https://other.test/x'];
        yield 'absolute, single slash' => ['http:/other.test/x', 'http:/other.test/x'];
        yield 'IPv6 link-local' => ['fe80::1', 'https://api.test/v1/fe80::1'];
        yield 'IPv6 with letters and query' => ['abcd:ef01::1?x=1', 'https://api.test/v1/abcd:ef01::1?x=1'];
        yield 'IPv6 with api key' => ['2a00:1450::1?api-key=K', 'https://api.test/v1/2a00:1450::1?api-key=K'];
        yield 'absolute, uppercase scheme' => ['HTTP://other.test/x', 'HTTP://other.test/x'];
        yield 'query-only continuation' => ['&ip=2a00::1', 'https://api.test/v1/&ip=2a00::1'];
    }

    #[Test]
    #[DataProvider('urls')]
    public function it_joins_relative_urls_with_base_uri(string $url, string $expected): void
    {
        $client = $this->recordingClient(['base_uri' => 'https://api.test/v1/']);

        $this->assertSame($expected, $this->requestedUrl($client, $url));
    }

    #[Test]
    public function it_appends_query_to_ipv6_path(): void
    {
        $client = $this->recordingClient(['base_uri' => 'https://api.test/', 'query' => ['api-key' => 'K']]);

        $this->assertSame('https://api.test/2a00:1450:4001::1?api-key=K', $this->requestedUrl($client, '2a00:1450:4001::1'));
    }

    /** `zend.exception_ignore_args=0` puts call arguments into traces that GeoIP logs. */
    #[Test]
    public function it_hides_secrets_from_exception_traces(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        $this->assertNotFalse($previous);

        try {
            // cURL rejects the unknown protocol without touching the network.
            new HttpClient(['query' => ['api-key' => 'SECRET-QUERY']])->get(
                'unsupported://api.test/?token=SECRET-URL',
                ['extra' => 'SECRET-EXTRA'],
                ['Authorization: Bearer SECRET-HEADER'],
            );
            $this->fail('Expected the request to fail');
        } catch (\RuntimeException $runtimeException) {
            $trace = $runtimeException->getTrace();
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }

        $this->assertNotEmpty($trace);
        $hasArgs = false;
        foreach ($trace as $frame) {
            if (isset($frame['args'])) {
                $hasArgs = true;
                $this->assertStringNotContainsString('SECRET', var_export($frame['args'], true));
            }
        }

        $this->assertTrue($hasArgs, 'Trace args must be collected for this test to be meaningful');
        $this->assertInstanceOf(\SensitiveParameterValue::class, $trace[0]['args'][2] ?? null);
    }

    #[Test]
    public function it_caps_response_size(): void
    {
        $options = $this->curlOptions([]);

        $this->assertSame(1024 * 1024, $options[\CURLOPT_MAXFILESIZE]);
    }

    /** Verbose cURL writes the request line, including API keys in query strings, to STDERR. */
    #[Test]
    public function it_does_not_enable_verbose_output(): void
    {
        $options = $this->curlOptions([]);

        $this->assertArrayNotHasKey(\CURLOPT_VERBOSE, $options);
    }

    /** @param array<string, mixed> $config */
    private function recordingClient(array $config): HttpClient
    {
        return new class ($config) extends HttpClient {
            /** @return array<int, mixed> */
            #[\Override]
            protected function curlOptions(string $url, array $headers): array
            {
                throw new UrlCaptured($url);
            }
        };
    }

    private function requestedUrl(HttpClient $client, string $url): string
    {
        try {
            $client->get($url);
        } catch (UrlCaptured $urlCaptured) {
            return $urlCaptured->url;
        }

        throw new \LogicException('URL was not captured');
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int, mixed>
     */
    private function curlOptions(array $config): array
    {
        return new class ($config) extends HttpClient {
            /** @return array<int, mixed> */
            public function options(): array
            {
                return $this->curlOptions('https://example.test/', []);
            }
        }->options();
    }
}

/** @internal */
final class UrlCaptured extends \RuntimeException
{
    public function __construct(public readonly string $url)
    {
        parent::__construct('captured');
    }
}
