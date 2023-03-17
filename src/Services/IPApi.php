<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use InteractionDesignFoundation\GeoIP\Exceptions\RequestFailedException;
use InteractionDesignFoundation\GeoIP\LocationResponse;

class IPApi extends AbstractService
{
    protected string $baseUrl = 'http://ip-api.com/';
   /**
     * An array of continents.
     *
     * @var array<string, string> $continents
     */
    protected array $continents;

    /** The "booting" method of the service.
     *
     * @throws \JsonException
     */
    public function boot(): void
    {
        $apiKey = config('geoip.services.ipapi.key');
        $lang = config('geoip.services.ipapi.lang');
        assert(is_string($apiKey) && is_string($lang));

        $this->query = [
            'fields' => '49663',
            'lang' => $lang,
            'key' => $apiKey
        ];

        $path = config('geoip.services.ipapi.continent_path');
        assert(is_string($path));

        if (file_exists($path)) {
            $content = file_get_contents($path);
            assert(is_string($content));
            /** @var array<string, string> $continents */
            $continents = json_decode(
                $content, true, 512, JSON_THROW_ON_ERROR
            );
            $this->continents = $continents;
        }
    }

    /** @see https://ip-api.com/docs/api:json The api documentation */
    public function locate(string $ip): LocationResponse
    {
        try {
            /** @var array<string, string> $json */
            $json = Http::get($this->formatUrl("json/$ip"))->throw()->json();
        } catch (RequestException $requestException) {
            /** @var array<string, mixed> $errors */
            $errors = $requestException->response->json();
            throw RequestFailedException::requestFailed($errors);
        }

        $countryCode = $json['countryCode'];

        return new LocationResponse(
            $json['query'],
            $countryCode,
            $json['country'],
            $json['city'],
            $json['region'],
            $json['regionName'],
            $json['zip'],
            (float) $json['lat'],
            (float) $json['lon'],
            $json['timezone'],
            $this->getContinent($countryCode),
            $json['currency'] ?? 'Unknown',
            false,
            false
        );
    }

    /**
     * Update function for service.
     *
     * @return string
     * @throws \Exception
     */
    public function update(): string
    {
        $data = file_get_contents('https://dev.maxmind.com/static/csv/codes/country_continent.csv');

        if ($data === false) {
            throw new \RuntimeException('Unable to get continent data.');
        }

        $lines = explode(PHP_EOL, $data);
        array_shift($lines);

        $output = [];

        foreach ($lines as $line) {
            $arr = str_getcsv($line);

            if (count($arr) < 2) {
                continue;
            }

            [$key, $value] = $arr;
            assert($key !== null);
            $output[$key] = $value;
        }

        $path = config('geoip.services.ipapi.continent_path');
        assert(is_string($path));

        file_put_contents($path, json_encode($output, JSON_THROW_ON_ERROR));

        return "Continent file [$path] updated.";
    }

    /** Get continent based on country code. */
    private function getContinent(string $code): string
    {
        $continent = Arr::get($this->continents, $code, 'Unknown');
        assert(is_string($continent));
        return $continent;
    }
}