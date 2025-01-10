<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use Exception;
use Illuminate\Support\Arr;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;

class IPApi extends AbstractService
{
    /**
     * Http client instance.
     *
     * @var HttpClient
     */
    protected $client;

    /**
     * An array of continents.
     *
     * @var array
     */
    protected $continents;

    /**
     * The "booting" method of the service.
     *
     * @return void
     */
    public function boot(): void
    {
        $base = [
            'base_uri' => 'http://ip-api.com/',
            'headers' => [
                'User-Agent' => 'Laravel-GeoIP-InteractionDesignFoundation',
            ],
            'query' => [
                'fields' => 49663,
                'lang' => $this->config('lang', ['en']),
            ],
        ];

        // Using the Pro service
        if ($this->config('key')) {
            $base['base_uri'] = ($this->config('secure') ? 'https' : 'http') . '://pro.ip-api.com/';
            $base['query']['key'] = $this->config('key');
        }

        $this->client = new HttpClient($base);

        // Set continents
        if (file_exists($this->config('continent_path'))) {
            $this->continents = json_decode(file_get_contents($this->config('continent_path')), true);
        }
    }

    /**
     * {@inheritDoc}
     * @throws \RuntimeException
     */
    public function locate($ip)
    {
        // Get data from the client
        $data = $this->client->get('json/' . $ip);

        // Verify server response
        if ($this->client->getErrors() !== null) {
            throw new \RuntimeException('Unexpected ip-api.com response: ' . $this->client->getErrors());
        }

        // Parse body content
        $json = json_decode((string) $data[0]);
        if (! is_object($json) || ! property_exists($json, 'status')) {
            throw new \RuntimeException('Unexpected ip-api.com response: ' . $json->message);
        }

        // Verify response status
        if ($json->status !== 'success') {
            throw new \RuntimeException('Failed ip-api.com response: ' . $json->message);
        }

        return $this->hydrate([
            'ip' => $ip,
            'iso_code' => $json->countryCode,
            'country' => $json->country,
            'city' => $json->city,
            'state' => $json->region,
            'state_name' => $json->regionName,
            'postal_code' => $json->zip,
            'lat' => $json->lat,
            'lon' => $json->lon,
            'timezone' => $json->timezone,
            'continent' => $this->getContinent($json->countryCode),
        ]);
    }

    /**
     * Update function for service.
     *
     * @return string
     * @throws Exception
     */
    public function update(): string
    {
        $data = $this->client->get('https://dev.maxmind.com/static/csv/codes/country_continent.csv');

        // Verify server response
        if ($this->client->getErrors() !== null) {
            throw new Exception($this->client->getErrors());
        }

        $lines = explode("\n", (string) $data[0]);

        array_shift($lines);

        $output = [];

        foreach ($lines as $line) {
            $arr = str_getcsv((string) $line);

            if (count($arr) < 2) {
                continue;
            }

            $output[$arr[0]] = $arr[1];
        }

        // Get path
        $path = $this->config('continent_path');

        file_put_contents($path, json_encode($output));

        return sprintf('Continent file (%s) updated.', $path);
    }

    /**
     * Get a continent based on country code.
     *
     * @param string $code
     *
     * @return string
     */
    private function getContinent(string $code): string
    {
        return Arr::get($this->continents, $code, 'Unknown');
    }
}
