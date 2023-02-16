<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use InteractionDesignFoundation\GeoIP\Contracts\Client;
use InteractionDesignFoundation\GeoIP\Exceptions\RequestFailedException;
use InteractionDesignFoundation\GeoIP\Location;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;

/**
 * Class GeoIP
 * @package InteractionDesignFoundation\GeoIP\Services
 */
class IPData extends AbstractService
{
    /** Http client instance. */
    protected Client $client;

    /** The "booting" method of the service. */
    public function boot(): void
    {
        $this->client = App::make(Client::class);
        $this->client->setConfig([
            'base_uri' => 'https://api.ipdata.co/',
            'query' => [
                'api-key' => $this->config('key'),
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function locate(string $ip): Location
    {
        // Get data from client
        $json = $this->client->get($ip);

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
            'timezone' => Arr::get($json, 'time_zone.name'),
            'continent' => $json['continent_code'],
            'currency' => Arr::get($json, 'currency.code'),
        ]);
    }
}
