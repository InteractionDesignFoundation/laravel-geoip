<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use Exception;
use Illuminate\Support\Facades\App;
use InteractionDesignFoundation\GeoIP\Contracts\Client;
use InteractionDesignFoundation\GeoIP\Location;

/**
 * Class GeoIP
 * @package InteractionDesignFoundation\GeoIP\Services
 */
class IPFinder extends AbstractService
{
    protected Client $client;

    /**
     * The "booting" method of the service.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->client = App::make(Client::class);
        $this->client->setConfig([
            'base_uri' => 'https://api.ipapi.com/api/',
        ]);
        $this->client->setDefaultQueryParameters([
            'token' => $this->config('key')
        ]);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function locate(string $ip): Location
    {
        // Get data from client
        $data = $this->client->get($ip);

        return $this->hydrate($data);
    }
}
