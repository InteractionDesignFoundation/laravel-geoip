<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use InteractionDesignFoundation\GeoIP\Contracts\LocationProvider;

abstract class AbstractService implements LocationProvider
{
    protected string $baseUrl = "PLEASE_SET_THE_CORRECT_BASE_URL";

    /** @var array<string, string> $query */
    protected array $query = [];

    /** @var array<string, string> $headers */
    protected array $headers = [];

    /** Create a new service instance. */
    public function __construct()
    {
        $this->boot();
    }

    /** The "booting" method of the service. */
    abstract public function boot(): void;

    protected function formatUrl(string $url): string
    {
        // Check for URL scheme
        if (parse_url($url, PHP_URL_SCHEME) === null) {
            $url = $this->baseUrl.$url;
        }
        return $url;
    }
}