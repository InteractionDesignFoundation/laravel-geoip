<?php declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Contracts;

interface Client
{
    /** Set the HTTP client configuration. */
    public function setConfig(array $config): self;

    /** Set the parameters that should be sent in every request. */
    public function setDefaultQueryParameters(array $query): self;

    /** Performs a GET request. */
    public function get(string $url, array $query = [], array $headers = []): array;
}