<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use GeoIp2\WebService\Client;
use InteractionDesignFoundation\GeoIP\Location;

class MaxMindWebService extends AbstractService
{
    protected Client $client;

    /**
     * The "booting" method of the service.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->client = new Client(
            $this->config('user_id'),
            $this->config('license_key'),
            $this->config('locales', ['en'])
        );
    }

    /** @inheritdoc */
    public function locate(string $ip): Location
    {
        $record = $this->client->city($ip);

        return $this->hydrate([
            'ip' => $ip,
            'iso_code' => $record->country->isoCode,
            'country' => $record->country->name,
            'city' => $record->city->name,
            'state' => $record->mostSpecificSubdivision->isoCode,
            'state_name' => $record->mostSpecificSubdivision->name,
            'postal_code' => $record->postal->code,
            'lat' => $record->location->latitude,
            'lon' => $record->location->longitude,
            'timezone' => $record->location->timeZone,
            'continent' => $record->continent->code,
        ]);
    }
}