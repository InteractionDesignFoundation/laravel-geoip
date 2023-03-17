<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InteractionDesignFoundation\GeoIP\Exceptions\RequestFailedException;
use InteractionDesignFoundation\GeoIP\LocationResponse;

class IPFinder extends AbstractService
{
    protected string $baseUrl = 'http://api.ipapi.com/api/';

    /** The "booting" method of the service. */
    public function boot(): void
    {
        $apiKey = config('geoip.services.ipfinder.key');
        assert(is_string($apiKey));

        $this->query = [
            'token' => $apiKey,
        ];
    }

    public function locate(string $ip): LocationResponse
    {
        try {
            /** @var array<string, string> $json */
            $json = Http::get($this->formatUrl($ip), $this->query)->throw()->json();
        } catch (RequestException $requestException) {
            /** @var array<string, mixed> $errors */
            $errors = $requestException->response->json();
            throw RequestFailedException::requestFailed($errors);
        }

        return new LocationResponse(
            $json['ip'],
            $json['country_code'],
            $json['country_name'],
            $json['city'],
            $json['region_code'],
            $json['region_name'],
            $json['zip'],
            (float) $json['latitude'],
            (float) $json['longitude'],
            'Unknown',
            $json['continent_code'],
            'Unknown',
            false,
            false,
        );
    }
}
