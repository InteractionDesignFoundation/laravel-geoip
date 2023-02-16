<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use InteractionDesignFoundation\GeoIP\Contracts\Client;
use InteractionDesignFoundation\GeoIP\Location;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;

class IPGeoLocation extends AbstractService
{
    /** Http client instance. */
    protected Client $client;

    /** The "booting" method of the service.  */
    public function boot(): void
    {
        $this->client = App::make(Client::class);

        $baseUri = 'https://api.ipgeolocation.io/';
        $this->client->setConfig([
            'base_uri' => $baseUri,
        ]);

        if ($this->config('key')) {
            $this->client->setConfig([
                'base_uri' => "{$baseUri}ipgeo?apiKey=" . $this->config('key')
            ]);
        }
    }

    public function locate(string $ip): Location
    {
        // Get data from client
        $data = $this->client->get('/', ['ip' => $ip]);

        return $this->hydrate($data);
    }
}
