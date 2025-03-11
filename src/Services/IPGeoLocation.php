<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use Exception;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;

class IPGeoLocation extends AbstractService
{
    /**
     * Http client instance.
     *
     * @var HttpClient
     */
    protected $client;

    /**
     * The "booting" method of the service.
     *
     * @return void
     */
    #[\Override]
    public function boot(): void
    {
        $base = [
            'base_uri' => 'https://api.ipgeolocation.io/',
        ];

        if ($this->config('key')) {
            $base['base_uri'] = $base['base_uri'] . 'ipgeo?apiKey=' . $this->config('key');
        }

        $this->client = new HttpClient($base);
    }

    /** {@inheritDoc} */
    #[\Override]
    public function locate($ip): \InteractionDesignFoundation\GeoIP\Location
    {
        // Get data from a client
        $data = $this->client->get('&ip=' . $ip);

        // Verify server response
        if ($this->client->getErrors() !== null) {
            throw new Exception('Request failed (' . $this->client->getErrors() . ')');
        }

        // Parse body content
        $json = json_decode((string) $data[0], true);

        return $this->hydrate($json);
    }
}
