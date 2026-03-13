<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use InteractionDesignFoundation\GeoIP\Support\HttpClient;

/**
 * @psalm-api
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
            throw new \RuntimeException('Request failed ('.($this->client->getErrors() ?? '').')');
        }

        $json = json_decode((string) $data[0]);

        if (! is_object($json)) {
            throw new \RuntimeException('Unexpected ip2location.io response');
        }

        if (property_exists($json, 'error')) {
            /** @var object{error_message?: string} $error */
            $error = $json->error;
            throw new \RuntimeException('IP2Location.io API error: '.($error->error_message ?? 'Unknown error'));
        }

        return $this->hydrate([
            'ip' => $ip,
            'iso_code' => isset($json->country_code) ? (string) $json->country_code : null,
            'country' => isset($json->country_name) ? (string) $json->country_name : null,
            'city' => isset($json->city_name) ? (string) $json->city_name : null,
            'state' => null,
            'state_name' => isset($json->region_name) ? (string) $json->region_name : null,
            'postal_code' => isset($json->zip_code) ? (string) $json->zip_code : null,
            'lat' => isset($json->latitude) ? (float) $json->latitude : null,
            'lon' => isset($json->longitude) ? (float) $json->longitude : null,
            'timezone' => isset($json->time_zone) ? (string) $json->time_zone : null,
            'continent' => null,
        ]);
    }
}
