<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use InteractionDesignFoundation\GeoIP\Location;
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
    public function boot(): void
    {
        $base = [
            'base_uri' => 'https://api.ipgeolocation.io/',
        ];

        if ($this->config('key')) {
            $base['base_uri'] = "{$base['base_uri']}ipgeo?apiKey=" . $this->config('key');
        }

        $this->client = new HttpClient($base);
    }


    public function locate(string $ip): Location
    {
        // Get data from client
        $data = $this->client->get('&ip=' . $ip);

        // Verify server response
        if ($this->client->getErrors() !== null) {
            throw new \Exception('Request failed (' . $this->client->getErrors() . ')');
        }

        // Parse body content
        $json = json_decode($data[0], true);

        return $this->hydrate($json);
    }
}
