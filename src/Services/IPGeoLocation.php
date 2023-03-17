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
            /** @var array<string, string> $json */
            $json = Http::get($this->baseUrl, $this->query)->throw()->json();
        } catch (RequestException $requestException) {
            /** @var array<string, mixed> $errors */
            $errors = $requestException->response->json();
            throw RequestFailedException::requestFailed($errors);
        }

        return new LocationResponse(
            $json['ip'],
            $json['country_code2'],
            $json['country_name'],
            $json['city'],
            'Unknown',
            $json['state_prov'],
            $json['zipcode'],
            (float) $json['latitude'],
            (float) $json['longitude'],
            $json['time_zone']['name'] ?? 'Unknown',
            $json['continent_code'],
            $json['currency']['code'] ?? 'Unknown',
            false,
            false,
        );
    }
}
