<?php

namespace InteractionDesignFoundation\GeoIP\Support;

use Illuminate\Support\Arr;

class HttpClient
{
    /** Request configurations. */
    private array $config = [];

    /** Last request http status. */
    protected int $http_code = 200;

    /** Last request error string. */
    protected string $errors = "";

    /**
     * HttpClient constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /** Perform a get request. */
    public function get(string $url, array $query = [], array $headers = []): array
    {
        return $this->execute('GET', $this->buildGetUrl($url, $query), [], $headers);
    }

    /** Execute the curl request */
    public function execute(string $method, string $url, array $query = [], array $headers = []): array
    {
        // Merge global and request headers
        $headers = array_merge(
            Arr::get($this->config, 'headers', []),
            $headers
        );

        // Merge global and request queries
        $query = array_merge(
            Arr::get($this->config, 'query', []),
            $query
        );

        $this->errors = null;

        $curl = curl_init();

        // Set options
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->getUrl($url),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HEADER => 1,
            CURLINFO_HEADER_OUT => 1,
            CURLOPT_VERBOSE => 1,
        ]);

        // Setup method specific options
        switch ($method) {
            case 'PUT':
            case 'PATCH':
            case 'POST':
                curl_setopt_array($curl, [
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $query,
                ]);
                break;

            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;

            default:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
                break;
        }

        // Make request
        curl_setopt($curl, CURLOPT_HEADER, true);
        $response = curl_exec($curl);

        // Set HTTP response code
        $this->http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Set errors if there are any
        if (curl_errno($curl)) {
            $this->errors = curl_error($curl);
        }

        // Parse body
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        curl_close($curl);

        return [$body, $this->parseHeaders($header)];
    }

    /** Check if the curl request ended up with errors */
    public function hasErrors(): bool
    {
        return is_null($this->errors) === false;
    }

    /** Get curl errors */
    public function getErrors(): string
    {
        return $this->errors;
    }

    /** Parse string headers into array */
    private function parseHeaders(string $headers): array
    {
        $result = [];
        $headersArray = preg_split("/\\r\\n|\\r|\\n/", $headers);
        assert(is_array($headersArray));

        foreach ($headersArray as $row) {
            $header = explode(':', $row, 2);

            if (count($header) === 2) {
                $result[$header[0]] = trim($header[1]);
            }
            else {
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
            $url = Arr::get($this->config, 'base_uri') . $url;
        }

        return $url;
    }

    /** Build a GET request string. */
    private function buildGetUrl(string $url, array $query = []): string
    {
        $queryConfig = Arr::get($this->config, 'query', []);
        assert(is_array($queryConfig));
        // Merge global and request queries
        $query = array_merge(
            $queryConfig,
            $query
        );

        // Append query
        if ($queryString = http_build_query($query)) {
            $url .= strpos($url, '?') ? $queryString : "?$queryString";
        }

        return $url;
    }
}