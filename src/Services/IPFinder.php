<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use InteractionDesignFoundation\GeoIP\Support\HttpClient;

class IPFinder extends AbstractService
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
            'base_uri' => 'https://api.ipfinder.io/v1/',
            'headers' => [
                'User-Agent' => 'Laravel-GeoIP-InteractionDesignFoundation',
            ],
            'query' => [
                'token' => $this->config('key'),
            ],
        ]);
    }

    /**
     * {@inheritDoc}
     * @throws \Exception
     */
    #[\Override]
    public function locate($ip): \InteractionDesignFoundation\GeoIP\Location
    {
        // Get data from client
        $data = $this->client->get($ip);

        // Verify server response
        if ($this->client->getErrors() !== null || empty($data[0])) {
            throw new \Exception('Request failed (' . $this->client->getErrors() . ')');
        }

        $json = json_decode((string) $data[0], true);

        return $this->hydrate($json);
    }
}
