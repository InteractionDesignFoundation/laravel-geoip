<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use InteractionDesignFoundation\GeoIP\Support\HttpClient;

/**
 * @internal
 */
class IP2Location extends AbstractService
{
    protected HttpClient $client;

    #[\Override]
    public function boot(): void
    {
        $this->ensureConfigurationParameterDefined('key');

        $this->client = new HttpClient([
            'base_uri' => 'https://api.ip2location.io/',
            'query' => [
                'key' => $this->config('key'),
            ],
        ]);
    }

    /**
     * {@inheritDoc}
     * @throws \RuntimeException
     */
    #[\Override]
    public function locate($ip): \InteractionDesignFoundation\GeoIP\Location
    {
        $data = $this->client->get('', [
            'ip' => $ip,
        ]);

        if ($this->client->getErrors() !== null) {
            throw new \RuntimeException('Request failed (' . $this->client->getErrors() . ')');
        }

        $json = json_decode((string) $data[0]);

        if (! is_object($json)) {
            throw new \RuntimeException('Unexpected ip2location.io response');
        }

        if (property_exists($json, 'error')) {
            throw new \RuntimeException('IP2Location.io API error: ' . ($json->error->error_message ?? 'Unknown error'));
        }

        return $this->hydrate([
            'ip' => $ip,
            'iso_code' => $json->country_code ?? null,
            'country' => $json->country_name ?? null,
            'city' => $json->city_name ?? null,
            'state' => null,
            'state_name' => $json->region_name ?? null,
            'postal_code' => $json->zip_code ?? null,
            'lat' => $json->latitude ?? null,
            'lon' => $json->longitude ?? null,
            'timezone' => $json->time_zone ?? null,
            'continent' => null,
        ]);
    }
}
