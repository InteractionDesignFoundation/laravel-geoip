<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use InteractionDesignFoundation\GeoIP\Location;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;

/**
 * Class GeoIP
 * @package InteractionDesignFoundation\GeoIP\Services
 */
class IPFinder extends AbstractService
{
    /** Http client instance. */
    protected HttpClient $client;

    /** The "booting" method of the service. */
    public function boot(): void
    {
        $this->client = new HttpClient([
            'base_uri' => 'https://api.ipfinder.io/v1/',
            'headers' => [
                'User-Agent' => 'Laravel-GeoIP-InteractionDesignFoundation',
            ],
            'query'    => [
                'token' => $this->config('key'),
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    public function locate(string $ip): Location
    {
        // Get data from client
        $data = $this->client->get($ip);

        // Verify server response
        if ($this->client->getErrors() !== null || empty($data[0])) {
            throw new \Exception('Request failed (' . $this->client->getErrors() . ')');
        }

        $json = json_decode($data[0], true);

        return $this->hydrate($json);
    }
}
