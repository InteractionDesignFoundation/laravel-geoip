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

        $this->assertSame(2000, $options[CURLOPT_CONNECTTIMEOUT_MS]);
        $this->assertSame(5000, $options[CURLOPT_TIMEOUT_MS]);
    }

    #[Test]
    public function it_applies_configured_timeouts(): void
    {
        $options = $this->curlOptions(['connect_timeout' => 0.5, 'timeout' => '3']);

        $this->assertSame(500, $options[CURLOPT_CONNECTTIMEOUT_MS]);
        $this->assertSame(3000, $options[CURLOPT_TIMEOUT_MS]);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidTimeouts(): iterable
    {
        yield 'empty string' => [''];
        yield 'zero' => [0];
        yield 'non-numeric' => ['invalid'];
        yield 'negative' => [-1];
        yield 'null' => [null];
    }

    /** A 0 would mean "no deadline" to cURL, so an unusable value must not reach it. */
    #[Test]
    #[DataProvider('invalidTimeouts')]
    public function it_falls_back_to_defaults_for_invalid_timeouts(mixed $invalid): void
    {
        $options = $this->curlOptions(['connect_timeout' => $invalid, 'timeout' => $invalid]);

        $this->assertSame(2000, $options[CURLOPT_CONNECTTIMEOUT_MS]);
        $this->assertSame(5000, $options[CURLOPT_TIMEOUT_MS]);
    }

    #[Test]
    public function it_caps_response_size(): void
    {
        $options = $this->curlOptions([]);

        $this->assertSame(1024 * 1024, $options[CURLOPT_MAXFILESIZE]);
    }

    /** Verbose cURL writes the request line, including API keys in query strings, to STDERR. */
    #[Test]
    public function it_does_not_enable_verbose_output(): void
    {
        $options = $this->curlOptions([]);

        $this->assertArrayNotHasKey(CURLOPT_VERBOSE, $options);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int, mixed>
     */
    private function curlOptions(array $config): array
    {
        return (new class ($config) extends HttpClient {
            /** @return array<int, mixed> */
            public function options(): array
            {
                return $this->curlOptions('https://example.test/', []);
            }
        })->options();
    }
}
