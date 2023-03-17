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
            /** @var array<string, string> $json */
            $json = Http::get($this->formatUrl($ip), $this->query)->throw()->json();
        } catch (RequestException $requestException) {
            /** @var array<string, mixed> $errors */
            $errors = $requestException->response->json();
            throw RequestFailedException::requestFailed($errors);
        }

        return new LocationResponse(
            $ip,
            $json['country_code'],
            $json['country_name'],
            $json['city'],
            $json['region_code'],
            $json['region'],
            $json['postal'],
            (float) $json['latitude'],
            (float) $json['longitude'],
            Arr::get($json, 'time_zone.name', 'Unknown'),
            Arr::get($json, 'continent_code', 'Unknown'),
            Arr::get($json, 'currency.code', 'Unknown'),
            false,
            false
        );
    }
}
