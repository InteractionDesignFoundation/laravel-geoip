<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use GeoIp2\Model\City;
use GeoIp2\WebService\Client;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;

/** @psalm-api */
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

        $licenseKey = $this->config('license_key');
        assert(is_string($licenseKey), 'Invalid "license_key" config value');
        /** @var list<string> $locales */
        $locales = $this->config('locales', ['en']);

        $this->client = new Client((int) $this->config('user_id'), $licenseKey, $locales, [
            'connectTimeout' => HttpClient::resolveTimeout($this->config('connect_timeout'), HttpClient::DEFAULT_CONNECT_TIMEOUT),
            'timeout' => HttpClient::resolveTimeout($this->config('timeout'), HttpClient::DEFAULT_TIMEOUT),
        ]);
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
     * @return array<string, array{country: string|null, state_name: string|null, city: string|null}>
     */
    private function getLocalizations(City $record): array
    {
        $localizations = [];

        /** @var list<string> $locales */
        $locales = $this->config('locales', ['en']);
        foreach ($locales as $lang) {
            $localizations[$lang] = [
                'country' => $record->country->names[$lang] ?? null,
                'state_name' => $record->mostSpecificSubdivision->names[$lang] ?? null,
                'city' => $record->city->names[$lang] ?? null,
            ];
        }

        return $localizations;
    }
}
