<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use InteractionDesignFoundation\GeoIP\Contracts\Client;
use InteractionDesignFoundation\GeoIP\Location;
use InteractionDesignFoundation\GeoIP\LocationResponse;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;

class IPApi extends AbstractService
{
    /**
     * Http client instance. */
    protected HttpClient $client;

    /** An array of continents. */
    protected array $continents;

    /** The "booting" method of the service. */
    public function boot(): void
    {
        $this->client = App::make(Client::class);
        $this->client->setConfig([
            'base_uri' => 'http://ip-api.com/'
        ]);
        $this->client->setDefaultQueryParameters($this->getDefaultQueryParameters());

        // Using the Pro service
        if ($this->config('key')) {
            $this->client->setConfig([
                'base_uri' => $this->config('secure') ? 'https' : 'http' . '://pro.ip-api.com/'
            ]);
            $this->client->setDefaultQueryParameters(array_merge(
                $this->getDefaultQueryParameters(),
                ['key' => $this->config('key')]
            ));
        }

        // Set continents
        if (file_exists($this->config('continent_path'))) {
            $this->continents = json_decode(file_get_contents($this->config('continent_path')), true, 512, JSON_THROW_ON_ERROR);
        }
    }

    /** @inheritDoc */
    public function locate($ip): Location|LocationResponse
    {
        // Get data from client
        $data = $this->client->get('json/' . $ip);

        return $this->hydrate([
            'ip' => $ip,
            'iso_code' => $data['countryCode'],
            'country' => $data['country'],
            'city' => $data['city'],
            'state' => $data['region'],
            'state_name' => $data['regionName'],
            'postal_code' => $data['zip'],
            'lat' => $data['lat'],
            'lon' => $data['lon'],
            'timezone' => $data['timezone'],
            'continent' => $this->getContinent($data['countryCode']),
        ]);
    }

    /**
     * Update function for service.
     *
     * @throws \Exception
     */
    public function update(): string
    {
        $data = file_get_contents('https://dev.maxmind.com/static/csv/codes/country_continent.csv');
        $lines = explode("\n", $data);
        array_shift($lines);

        $output = [];

        foreach ($lines as $line) {
            $arr = str_getcsv($line);

            if (count($arr) < 2) {
                continue;
            }

            $output[$arr[0]] = $arr[1];
        }

        // Get path
        $path = $this->config('continent_path');

        file_put_contents($path, json_encode($output, JSON_THROW_ON_ERROR));

        return "Continent file ({$path}) updated.";
    }

    /** Get continent based on country code. */
    private function getContinent(string $code): string
    {
        return Arr::get($this->continents, $code, 'Unknown');
    }

    private function getDefaultQueryParameters(): array
    {
        return [
            'fields' => 49663,
            'lang' => $this->config('lang', ['en']),
        ];
    }

    public function hydrate(array $attributes = []): Location|LocationResponse
    {
        if (config('geoip.should_use_dto_response', false)) {
            return LocationResponse::fromIPApi($attributes);
        }

        return new Location($attributes);
    }
}
