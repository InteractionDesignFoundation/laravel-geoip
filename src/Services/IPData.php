<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use InteractionDesignFoundation\GeoIP\Exceptions\RequestFailedException;
use InteractionDesignFoundation\GeoIP\LocationResponse;

final class IPData extends AbstractService
{
    protected string $baseUrl = 'https://api.ipdata.co/';

    /**
     * The "booting" method of the service.
     *
     * @return void
     */
    public function boot(): void
    {
        $apiKey = config('geoip.services.ipdata.key');
        assert(is_string($apiKey));

        $this->query = [
            'api-key' => $apiKey
        ];
    }

    public function locate(string $ip): LocationResponse
    {
        // Get data from client
        try {
            /** @var array<string, string|float|array<string, string>> $json */
            $json = Http::get($this->formatUrl($ip), $this->query)->throw()->json();
        } catch (RequestException $requestException) {
            /** @var array<string, mixed> $errors */
            $errors = $requestException->response->json();
            throw RequestFailedException::requestFailed($errors);
        }

        $countryCode = $json['country_code'];
        $countryName = $json['country_name'];
        $city = $json['city'];
        $regionCode = $json['region_code'];
        $region = $json['region'];
        $postal = $json['postal'];
        $continentCode = $json['continent_code'];
        $latitude = is_string($json['latitude']) || is_float($json['latitude']) ? (float) $json['latitude'] : 0.0;
        $longitude = is_string($json['longitude']) || is_float($json['longitude']) ? (float) $json['longitude'] : 0.0;
        $timezone = is_array($json['time_zone']) ? $json['time_zone']['name'] : 'Unknown';
        $currency = is_array($json['currency']) ? $json['currency']['code'] : 'Unknown';

        assert(is_string($countryCode) && is_string($countryName) && is_string($city) && is_string($regionCode) && is_string($region) && is_string($postal) && is_string($continentCode));

        return new LocationResponse(
            $ip,
            $countryCode,
            $countryName,
            $city,
            $regionCode,
            $region,
            $postal,
            $latitude,
            $longitude,
            $timezone,
            $continentCode,
            $currency,
            false,
            false
        );
    }
}
