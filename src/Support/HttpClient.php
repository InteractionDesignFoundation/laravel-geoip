<?php declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Support;

use Illuminate\Support\Arr;

/**
 * @psalm-api
 */
class HttpClient
{
    /** Last request http status. */
    private int $http_code = 200;

    /** Last request error string. */
    private ?string $errors = null;

    /** @param array<string, mixed> $config */
    public function __construct(/** Request configurations. */ private readonly array $config = []) {}

    /**
     * Perform a get request.
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
     * @param array<string, mixed> $query
     * @param array<int, string> $headers
     * @return array{string, array<array-key, string>}
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

        // Set options
        curl_setopt_array($curl, [
            \CURLOPT_URL => $this->getUrl($url),
            \CURLOPT_HTTPHEADER => $headers,
            \CURLOPT_CONNECTTIMEOUT => 20,
            \CURLOPT_TIMEOUT => 90,
            \CURLOPT_RETURNTRANSFER => 1,
            \CURLOPT_SSL_VERIFYPEER => true,
            \CURLOPT_SSL_VERIFYHOST => 2,
            \CURLOPT_HEADER => 1,
            \CURLINFO_HEADER_OUT => 1,
            \CURLOPT_VERBOSE => 1,
        ]);

        match ($method) {
            'PUT', 'PATCH', 'POST' => curl_setopt_array($curl, [
                \CURLOPT_CUSTOMREQUEST => $method,
                \CURLOPT_POST => true,
                \CURLOPT_POSTFIELDS => $query,
            ]),
            'DELETE' => curl_setopt($curl, \CURLOPT_CUSTOMREQUEST, 'DELETE'),
            default => curl_setopt($curl, \CURLOPT_CUSTOMREQUEST, 'GET'),
        };

        // Make request
        curl_setopt($curl, \CURLOPT_HEADER, true);
        $response = curl_exec($curl);
        if (! is_string($response)) {
            $curlError = curl_error($curl);
            throw new \RuntimeException(sprintf('Failed to make %s HTTP request: %s', $method, $curlError));
        }

        // Set HTTP response code
        $this->http_code = (int) curl_getinfo($curl, \CURLINFO_HTTP_CODE);

        // Set errors if there are any
        if (curl_errno($curl) !== 0) {
            $this->errors = curl_error($curl);
        }

        // Parse body
        $header_size = (int) curl_getinfo($curl, \CURLINFO_HEADER_SIZE);
        $header = mb_substr($response, 0, $header_size);
        $body = mb_substr($response, $header_size);

        curl_close($curl);

        return [$body, $this->parseHeaders($header)];
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
     * @return array<array-key, string>
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
                $result[$header[0]] = mb_trim($header[1]);
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
        if (parse_url($url, \PHP_URL_SCHEME) === null) {
            $url = (string) Arr::get($this->config, 'base_uri').$url;
        }

        return $url;
    }

    /**
     * Build a GET request string.
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
