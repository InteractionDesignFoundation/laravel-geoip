<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use Illuminate\Support\Arr;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;

/**
 * @psalm-api
 * @internal
 */
class IPApi extends AbstractService
{
    /**
     * Http client instance.
     *
     * @var HttpClient
     */
    protected HttpClient $client;

    /**
     * An array of continents.
     *
     * @var array
     */
    protected array $continents = [];

    /** The "booting" method of the service. */
    #[\Override]
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
    #[\Override]
    public function locate($ip): \InteractionDesignFoundation\GeoIP\Location
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

    /** Update function for service. */
    public function update(): string
    {
        $output = $this->countryContinentMap();

        // Get path
        $path = $this->config('continent_path');

        file_put_contents($path, json_encode($output));

        return sprintf('Continent file (%s) updated.', $path);
    }

    /**
     * ISO 3166 country code to continent code mapping.
     *
     * @return array<string, string>
     */
    private function countryContinentMap(): array
    {
        return [
            'AD' => 'EU', 'AE' => 'AS', 'AF' => 'AS', 'AG' => 'NA', 'AI' => 'NA',
            'AL' => 'EU', 'AM' => 'AS', 'AO' => 'AF', 'AP' => 'AS', 'AQ' => 'AN',
            'AR' => 'SA', 'AS' => 'OC', 'AT' => 'EU', 'AU' => 'OC', 'AW' => 'NA',
            'AX' => 'EU', 'AZ' => 'AS', 'BA' => 'EU', 'BB' => 'NA', 'BD' => 'AS',
            'BE' => 'EU', 'BF' => 'AF', 'BG' => 'EU', 'BH' => 'AS', 'BI' => 'AF',
            'BJ' => 'AF', 'BL' => 'NA', 'BM' => 'NA', 'BN' => 'AS', 'BO' => 'SA',
            'BQ' => 'NA', 'BR' => 'SA', 'BS' => 'NA', 'BT' => 'AS', 'BV' => 'AN',
            'BW' => 'AF', 'BY' => 'EU', 'BZ' => 'NA', 'CA' => 'NA', 'CC' => 'AS',
            'CD' => 'AF', 'CF' => 'AF', 'CG' => 'AF', 'CH' => 'EU', 'CI' => 'AF',
            'CK' => 'OC', 'CL' => 'SA', 'CM' => 'AF', 'CN' => 'AS', 'CO' => 'SA',
            'CR' => 'NA', 'CU' => 'NA', 'CV' => 'AF', 'CW' => 'NA', 'CX' => 'AS',
            'CY' => 'AS', 'CZ' => 'EU', 'DE' => 'EU', 'DJ' => 'AF', 'DK' => 'EU',
            'DM' => 'NA', 'DO' => 'NA', 'DZ' => 'AF', 'EC' => 'SA', 'EE' => 'EU',
            'EG' => 'AF', 'EH' => 'AF', 'ER' => 'AF', 'ES' => 'EU', 'ET' => 'AF',
            'EU' => 'EU', 'FI' => 'EU', 'FJ' => 'OC', 'FK' => 'SA', 'FM' => 'OC',
            'FO' => 'EU', 'FR' => 'EU', 'GA' => 'AF', 'GB' => 'EU', 'GD' => 'NA',
            'GE' => 'AS', 'GF' => 'SA', 'GG' => 'EU', 'GH' => 'AF', 'GI' => 'EU',
            'GL' => 'NA', 'GM' => 'AF', 'GN' => 'AF', 'GP' => 'NA', 'GQ' => 'AF',
            'GR' => 'EU', 'GS' => 'AN', 'GT' => 'NA', 'GU' => 'OC', 'GW' => 'AF',
            'GY' => 'SA', 'HK' => 'AS', 'HM' => 'AN', 'HN' => 'NA', 'HR' => 'EU',
            'HT' => 'NA', 'HU' => 'EU', 'ID' => 'AS', 'IE' => 'EU', 'IL' => 'AS',
            'IM' => 'EU', 'IN' => 'AS', 'IO' => 'AS', 'IQ' => 'AS', 'IR' => 'AS',
            'IS' => 'EU', 'IT' => 'EU', 'JE' => 'EU', 'JM' => 'NA', 'JO' => 'AS',
            'JP' => 'AS', 'KE' => 'AF', 'KG' => 'AS', 'KH' => 'AS', 'KI' => 'OC',
            'KM' => 'AF', 'KN' => 'NA', 'KP' => 'AS', 'KR' => 'AS', 'KW' => 'AS',
            'KY' => 'NA', 'KZ' => 'AS', 'LA' => 'AS', 'LB' => 'AS', 'LC' => 'NA',
            'LI' => 'EU', 'LK' => 'AS', 'LR' => 'AF', 'LS' => 'AF', 'LT' => 'EU',
            'LU' => 'EU', 'LV' => 'EU', 'LY' => 'AF', 'MA' => 'AF', 'MC' => 'EU',
            'MD' => 'EU', 'ME' => 'EU', 'MF' => 'NA', 'MG' => 'AF', 'MH' => 'OC',
            'MK' => 'EU', 'ML' => 'AF', 'MM' => 'AS', 'MN' => 'AS', 'MO' => 'AS',
            'MP' => 'OC', 'MQ' => 'NA', 'MR' => 'AF', 'MS' => 'NA', 'MT' => 'EU',
            'MU' => 'AF', 'MV' => 'AS', 'MW' => 'AF', 'MX' => 'NA', 'MY' => 'AS',
            'MZ' => 'AF', 'NA' => 'AF', 'NC' => 'OC', 'NE' => 'AF', 'NF' => 'OC',
            'NG' => 'AF', 'NI' => 'NA', 'NL' => 'EU', 'NO' => 'EU', 'NP' => 'AS',
            'NR' => 'OC', 'NU' => 'OC', 'NZ' => 'OC', 'OM' => 'AS', 'PA' => 'NA',
            'PE' => 'SA', 'PF' => 'OC', 'PG' => 'OC', 'PH' => 'AS', 'PK' => 'AS',
            'PL' => 'EU', 'PM' => 'NA', 'PN' => 'OC', 'PR' => 'NA', 'PS' => 'AS',
            'PT' => 'EU', 'PW' => 'OC', 'PY' => 'SA', 'QA' => 'AS', 'RE' => 'AF',
            'RO' => 'EU', 'RS' => 'EU', 'RU' => 'EU', 'RW' => 'AF', 'SA' => 'AS',
            'SB' => 'OC', 'SC' => 'AF', 'SD' => 'AF', 'SE' => 'EU', 'SG' => 'AS',
            'SH' => 'AF', 'SI' => 'EU', 'SJ' => 'EU', 'SK' => 'EU', 'SL' => 'AF',
            'SM' => 'EU', 'SN' => 'AF', 'SO' => 'AF', 'SR' => 'SA', 'SS' => 'AF',
            'ST' => 'AF', 'SV' => 'NA', 'SX' => 'NA', 'SY' => 'AS', 'SZ' => 'AF',
            'TC' => 'NA', 'TD' => 'AF', 'TF' => 'AN', 'TG' => 'AF', 'TH' => 'AS',
            'TJ' => 'AS', 'TK' => 'OC', 'TL' => 'AS', 'TM' => 'AS', 'TN' => 'AF',
            'TO' => 'OC', 'TR' => 'EU', 'TT' => 'NA', 'TV' => 'OC', 'TW' => 'AS',
            'TZ' => 'AF', 'UA' => 'EU', 'UG' => 'AF', 'UM' => 'OC', 'US' => 'NA',
            'UY' => 'SA', 'UZ' => 'AS', 'VA' => 'EU', 'VC' => 'NA', 'VE' => 'SA',
            'VG' => 'NA', 'VI' => 'NA', 'VN' => 'AS', 'VU' => 'OC', 'WF' => 'OC',
            'WS' => 'OC', 'XK' => 'EU', 'YE' => 'AS', 'YT' => 'AF', 'ZA' => 'AF',
            'ZM' => 'AF', 'ZW' => 'AF',
        ];
    }

    /** Get a continent based on country code. */
    private function getContinent(string $code): string
    {
        return Arr::get($this->continents, $code, 'Unknown');
    }
}
