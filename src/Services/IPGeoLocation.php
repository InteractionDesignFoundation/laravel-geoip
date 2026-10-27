<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use Exception;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;

/**
 * @psalm-import-type LocationArray from \InteractionDesignFoundation\GeoIP\Location
 * @psalm-api
 */
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
            $base['base_uri'] = $base['base_uri'].'ipgeo?apiKey='.(string) $this->config('key');
        }

        $this->client = new HttpClient($base);
    }

    /** {@inheritDoc} */
    #[\Override]
    public function locate($ip): \InteractionDesignFoundation\GeoIP\Location
    {
        // Get data from a client
        $data = $this->client->get('&ip='.$ip);

        // Verify server response
        $errors = $this->client->getErrors();
        if ($errors !== null) {
            throw new Exception('Request failed ('.$errors.')');
        }

        // Parse body content
        $json = json_decode($data[0], true);
        if (! is_array($json)) {
            throw new Exception('Unexpected ipgeolocation.io response');
        }

        /** @psalm-var LocationArray $json */

        return $this->hydrate($json);
    }
}
