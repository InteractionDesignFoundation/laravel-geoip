<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use GeoIp2\Model\City;
use GeoIp2\WebService\Client;
use Illuminate\Support\Arr;

class MaxMindWebService extends AbstractService
{
    /**
     * Service client instance.
     *
     * @var \GeoIp2\WebService\Client
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
        $this->ensureConfigurationParameterDefined(['user_id', 'license_key']);

        $this->client = new Client(
            (int) $this->config('user_id'),
            $this->config('license_key'),
            $this->config('locales', ['en'])
        );
    }

    /** {@inheritDoc} */
    #[\Override]
    public function locate($ip): \InteractionDesignFoundation\GeoIP\Location
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
            'localizations' => $this->getLocalizations($record),
        ]);
    }

    /**
     * Get localized country name, state name and city name based on config languages
     * @return array<string, string|null>
     */
    private function getLocalizations(City $record): array
    {
        $localizations = [];

        foreach ($this->config('locales', ['en']) as $lang) {
            $localizations[$lang]['country'] = Arr::get($record->country->names, $lang);
            $localizations[$lang]['state_name'] = Arr::get($record->mostSpecificSubdivision->names, $lang);
            $localizations[$lang]['city'] = Arr::get($record->city->names, $lang);
        }

        return $localizations;
    }
}
