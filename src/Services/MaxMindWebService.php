<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use GeoIp2\WebService\Client;
use InteractionDesignFoundation\GeoIP\LocationResponse;

class MaxMindWebService extends AbstractService
{
    /** Service client instance. */
    protected Client $client;

    /** The "booting" method of the service.  */
    public function boot(): void
    {
        $userId = config('geoip.services.maxmind_api.user_id');
        $licenseKey = config('geoip.services.maxmind_api.license_key');
        $locales = config('geoip.services.maxmind_api.locales', ['en']);
        assert(is_int($userId) && is_string($licenseKey) && is_array($locales));

        $this->client = new Client(
            $userId, $licenseKey, $locales
        );
    }

    /**
     * {@inheritdoc}
     */
    public function locate(string $ip): LocationResponse
    {
        $record = $this->client->city($ip);

        return new LocationResponse(
            $ip,
            (string) $record->country->isoCode,
            (string) $record->country->name,
            (string) $record->city->name,
            (string) $record->mostSpecificSubdivision->isoCode,
            (string) $record->mostSpecificSubdivision->name,
            (string) $record->postal->code,
            (float) $record->location->latitude,
            (float) $record->location->longitude,
            (string) $record->location->timeZone,
            (string) $record->continent->code,
            'Unknown',
            false,
            false,
        );
    }
}