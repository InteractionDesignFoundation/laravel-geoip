<?php

namespace InteractionDesignFoundation\GeoIP\Support;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use InteractionDesignFoundation\GeoIP\Contracts\Client;
use InteractionDesignFoundation\GeoIP\Exceptions\RequestFailedException;

class HttpClient implements Client
{
    /** Request configurations. */
    private array $config;

    /** Parameters that should be sent in the query string. */
    private array $query = [];

    /** HttpClient constructor */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function setDefaultQueryParameters(array $query): Client
    {
        $this->query = $query;

        return $this;
    }

    /** Perform a get request. */
    public function get(string $url, array $query = [], array $headers = []): array
    {
        try {
            return Http::get($this->formatUrl($url), $query)->throw()->json();
        } catch (RequestException $requestException) {
            throw RequestFailedException::requestFailed(
                $requestException->response->json()
            );
        }
    }

    /** Format the request URL. */
    private function formatUrl(string $url): string
    {
        // Check for URL scheme
        if (parse_url($url, PHP_URL_SCHEME) === null) {
            $url = Arr::get($this->config, 'base_uri') . $url;
        }

        return $url;
    }

    private function buildQuery(array $parameters = []): array
    {
        return array_merge($this->query, $parameters);
    }
}