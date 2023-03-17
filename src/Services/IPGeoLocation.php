<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InteractionDesignFoundation\GeoIP\Exceptions\RequestFailedException;
use InteractionDesignFoundation\GeoIP\LocationResponse;

/** @see https://ipgeolocation.io/documentation/ip-geolocation-api.html */
class IPGeoLocation extends AbstractService
{
    protected string $baseUrl = 'https://api.ipgeolocation.io/ipgeo/';
    /**
     * The "booting" method of the service.
     *
     * @return void
     */
    public function boot(): void
    {
        $apiKey = config('geoip.services.ipgeolocation.key');
        assert(is_string($apiKey));

        $this->query = ['apiKey' => $apiKey];
    }


    public function locate(string $ip): LocationResponse
    {
        $this->query['ip'] = $ip;

        try {
            /** @var array<string, string|float|array<string, string>> $json */
            $json = Http::get($this->baseUrl, $this->query)->throw()->json();
        } catch (RequestException $requestException) {
            /** @var array<string, mixed> $errors */
            $errors = $requestException->response->json();
            throw RequestFailedException::requestFailed($errors);
        }

        $latitude = is_string($json['latitude']) || is_float($json['latitude']) ? (float) $json['latitude'] : 0.0;
        $longitude = is_string($json['longitude']) || is_float($json['longitude']) ? (float) $json['longitude'] : 0.0;

        return new LocationResponse(
            is_string($json['ip']) ? $json['ip'] : 'Unknown',
            is_string($json['country_code2']) ? $json['country_code2'] : 'Unknown',
            is_string($json['country_name']) ? $json['country_name'] : 'Unknown',
            is_string($json['city']) ? $json['city'] : 'Unknown',
            'Unknown',
            is_string($json['state_prov']) ? $json['state_prov'] : 'Unknown',
            is_string($json['zipcode']) ? $json['zipcode'] : 'Unknown',
            $latitude,
            $longitude,
            $json['time_zone']['name'] ?? 'Unknown',
            is_string($json['continent_code']) ? $json['continent_code'] : 'Unknown',
            $json['currency']['code'] ?? 'Unknown',
            false,
            false,
        );
    }
}
