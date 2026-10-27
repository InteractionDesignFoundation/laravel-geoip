<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Support;

use Illuminate\Support\Arr;

/**
 * @psalm-api
 */
class HttpClient
{
    /** Seconds. A slow provider must not hold a web request for longer than this. */
    public const float DEFAULT_CONNECT_TIMEOUT = 2.0;

    /** Seconds, for the whole request. */
    public const float DEFAULT_TIMEOUT = 5.0;

    /**
     * Geolocation responses are tiny. Only enforced by cURL when the server announces Content-Length;
     * a chunked body is not cut off.
     */
    private const int MAX_RESPONSE_BYTES = 1024 * 1024;

    /** Last request http status. */
    private int $http_code = 200;

    /** Last request error string. */
    private ?string $errors = null;

    /**
     * HttpClient constructor.
     *
     * @param array<string, mixed> $config
     */
    public function __construct(
        /** Request configurations. */
        private readonly array $config = []
    ) {}

    /**
     * Perform a get request.
     *
     * @param array<string, mixed> $query
     * @param array<int, string> $headers
     * @return array{string, array<array-key, string>}
     */
    public function get(string $url, array $query = [], array $headers = []): array
    {
        return $this->execute('GET', $this->buildGetUrl($url, $query), [], $headers);
    }

    /**
     * Execute the curl request
     *
     * @param array<string, mixed> $query
     * @param array<int, string> $headers
     * @return array{string, array<array-key, string>}
     *
     * @throws \RuntimeException
     */
    public function execute(string $method, string $url, array $query = [], array $headers = []): array
    {
        /** @var array<int, string> $globalHeaders */
        $globalHeaders = Arr::get($this->config, 'headers', []);
        $headers = array_merge($globalHeaders, $headers);

        /** @var array<string, mixed> $globalQuery */
        $globalQuery = Arr::get($this->config, 'query', []);
        $query = array_merge($globalQuery, $query);

        $this->errors = null;

        $curl = curl_init();
        if ($curl === false) {
            throw new \RuntimeException('Failed to initialize cURL');
        }

        curl_setopt_array($curl, $this->curlOptions($this->getUrl($url), $headers));

        match ($method) {
            'PUT', 'PATCH', 'POST' => curl_setopt_array($curl, [
                \CURLOPT_CUSTOMREQUEST => $method,
                \CURLOPT_POST => true,
                \CURLOPT_POSTFIELDS => $query,
            ]),
            'DELETE' => curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE'),
            default => curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET'),
        };

        // Make request
        curl_setopt($curl, CURLOPT_HEADER, true);
        $response = curl_exec($curl);
        if (! is_string($response)) {
            $curlError = curl_error($curl);
            throw new \RuntimeException(sprintf('Failed to make %s HTTP request: %s', $method, $curlError));
        }

        // Set HTTP response code
        $this->http_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Set errors if there are any
        if (curl_errno($curl) !== 0) {
            $this->errors = curl_error($curl);
        }

        // Parse body
        $header_size = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        curl_close($curl);

        return [$body, $this->parseHeaders($header)];
    }

    /**
     * @param array<int, string> $headers
     * @return array<int, mixed>
     */
    protected function curlOptions(string $url, array $headers): array
    {
        return [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT_MS => $this->timeoutMs('connect_timeout', self::DEFAULT_CONNECT_TIMEOUT),
            CURLOPT_TIMEOUT_MS => $this->timeoutMs('timeout', self::DEFAULT_TIMEOUT),
            CURLOPT_MAXFILESIZE => self::MAX_RESPONSE_BYTES,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER => 1,
            CURLINFO_HEADER_OUT => 1,
            CURLOPT_VERBOSE => 1,
        ];
    }

    /**
     * Zero means "no deadline" to cURL and MaxMind, so anything but a positive number falls back to the default.
     *
     * @psalm-pure
     */
    public static function resolveTimeout(mixed $seconds, float $default): float
    {
        return is_numeric($seconds) && (float) $seconds > 0.0 ? (float) $seconds : $default;
    }

    private function timeoutMs(string $key, float $default): int
    {
        return (int) ceil(self::resolveTimeout(Arr::get($this->config, $key), $default) * 1000.0);
    }

    /** Check if the curl request ended up with errors */
    public function hasErrors(): bool
    {
        return $this->errors !== null;
    }

    /** Get curl errors */
    public function getErrors(): ?string
    {
        return $this->errors;
    }

    /** Get last curl HTTP code. */
    public function getHttpCode(): int
    {
        return $this->http_code;
    }

    /**
     * Parse string headers into array
     *
     * @return array<array-key, string>
     * @psalm-pure
     */
    private function parseHeaders(string $headers): array
    {
        $result = [];

        $rows = preg_split("/\\r\\n|\\r|\\n/", $headers);
        if ($rows === false) {
            return $result;
        }

        foreach ($rows as $row) {
            $header = explode(':', $row, 2);

            if (count($header) === 2) {
                $result[$header[0]] = trim($header[1]);
            } else {
                $result[] = $header[0];
            }
        }

        return $result;
    }

    /** Get request URL. */
    private function getUrl(string $url): string
    {
        // Check for URL scheme
        if (parse_url($url, PHP_URL_SCHEME) === null) {
            $url = (string) Arr::get($this->config, 'base_uri').$url;
        }

        return $url;
    }

    /**
     * Build a GET request string.
     *
     * @param array<string, mixed> $query
     */
    private function buildGetUrl(string $url, array $query = []): string
    {
        /** @var array<string, mixed> $globalQuery */
        $globalQuery = Arr::get($this->config, 'query', []);
        $query = array_merge($globalQuery, $query);

        $stringQuery = http_build_query($query);

        // Append query
        if ($stringQuery !== '' && $stringQuery !== '0') {
            $url .= str_contains($url, '?') ? $stringQuery : '?'.$stringQuery;
        }

        return $url;
    }
}
