<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use Exception;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;

/**
 * @psalm-api
 */
class IPData extends AbstractService
{
    /**
     * Http client instance.
     *
     * @var HttpClient
     */
    protected $client;

    /** The "booting" method of the service. */
    #[\Override]
    public function boot(): void
    {
        $this->ensureConfigurationParameterDefined('key');

        $this->client = new HttpClient([
            'base_uri' => 'https://api.ipdata.co/',
            'query' => [
                'api-key' => $this->config('key'),
            ],
        ]);
    }

    /**
     * {@inheritDoc}
     * @throws Exception
     */
    #[\Override]
    public function locate($ip): \InteractionDesignFoundation\GeoIP\Location
    {
        $ip = $this->assertValidIp($ip);

        // Get data from a client
        $data = $this->client->get($ip);

        // Verify server response
        $errors = $this->client->getErrors();
        if ($errors !== null) {
            throw new Exception('Request failed ('.$errors.')');
        }

        $this->ensureSuccessfulResponse($this->client, 'ipdata.co');

        if ($data[0] === '') {
            throw new Exception('Empty ipdata.co response');
        }

        $json = json_decode($data[0], true);
        if (! is_array($json)) {
            throw new Exception('Unexpected ipdata.co response');
        }

        /** @var array{country_code: string|null, country_name: string|null, city: string|null, region_code: string|null, region: string|null, postal: string|null, latitude: float|null, longitude: float|null, time_zone?: array{name?: string|null}|null, continent_code?: string|null, currency?: array{code?: string|null}|null} $json */

        return $this->hydrate([
            'ip' => $ip,
            'iso_code' => $json['country_code'],
            'country' => $json['country_name'],
            'city' => $json['city'],
            'state' => $json['region_code'],
            'state_name' => $json['region'],
            'postal_code' => $json['postal'],
            'lat' => $json['latitude'],
            'lon' => $json['longitude'],
            'timezone' => $json['time_zone']['name'] ?? null,
            'continent' => $json['continent_code'] ?? null,
            'currency' => $json['currency']['code'] ?? null,
        ]);
    }
}
