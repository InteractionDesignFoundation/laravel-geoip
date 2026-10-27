<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use InteractionDesignFoundation\GeoIP\Support\HttpClient;

/**
 * @psalm-import-type LocationArray from \InteractionDesignFoundation\GeoIP\Location
 * @psalm-api
 */
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
        $errors = $this->client->getErrors();
        if ($errors !== null || $data[0] === '') {
            throw new \Exception('Request failed ('.($errors ?? '').')');
        }

        $json = json_decode($data[0], true);
        if (! is_array($json)) {
            throw new \Exception('Unexpected ipfinder.io response');
        }

        /** @psalm-var LocationArray $json */

        return $this->hydrate($json);
    }
}
