<?php declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP;

final class LocationResponse
{
    public function __construct(
        public readonly string $ip,
        public readonly string $isoCode,
        public readonly string $country,
        public readonly string $city,
        public readonly string $state,
        public readonly string $stateName,
        public readonly string $postalCode,
        public readonly string $latitude,
        public readonly string $longitude,
        public readonly string $timezone,
        public readonly string $continent,
        public readonly string $continentCode,
        public readonly string $currency,
        public bool $default,
        public bool $cached,
    ) {
    }

    public static function fromIPApi(array $data, bool $default = false, bool $cached = false): self
    {
        return self::fromBaseData($data, $default, $cached);
    }

    public static function fromIPData(array $data, bool $default = false, bool $cached = false): self
    {
        return self::fromBaseData($data, $default, $cached);
    }

    public static function fromIPFinder(array $data, bool $default = false, bool $cached = false): self
    {
        return new self(
            (string) $data['ip'],
            $data['country_code'],
            $data['country_name'],
            $data['city'],
            $data['region_code'],
            $data['region_name'],
            $data['zip'],
            (string) $data['latitude'],
            (string) $data['longitude'],
            $data['time_zone']['name'] ?? '',
            $data['continent_name'],
            $data['continent_code'],
            $data['currency']['code'] ?? '',
            $default,
            $cached
        );
    }

    public static function fromIpGeoLocation(array $data, bool $default = false, bool $cached = false): self
    {
        return new self(
            $data['ip'],
            $data['country_code2'],
            $data['country_name'],
            $data['city'],
          '',
            $data['state_prov'],
            $data['zipcode'],
            $data['latitude'],
            $data['longitude'],
            $data['time_zone']['name'] ?? '',
            $data['continent_name'],
            $data['continent_code'],
            $data['currency']['code'] ?? '',
            $default,
            $cached
        );
    }

    public static function fromMaxMindDatabase(array $data, bool $default = false, bool $cached = false): self
    {
        return self::fromBaseData($data, $default, $cached);
    }

    public static function fromMaxMindWebservice(array $data, bool $default = false, bool $cached = false): self
    {
        return self::fromBaseData($data, $default, $cached);
    }

    private static function fromBaseData(array $data, bool $default = false, bool $cached = false): self
    {
        return new self(
            (string) $data['ip'],
            $data['iso_code'],
            $data['country'],
            $data['city'],
            $data['state'],
            $data['state_name'],
            $data['postal_code'] ?? '',
            (string) $data['lat'],
            (string) $data['lon'],
            $data['timezone'] ?? '',
            '',
            $data['continent'],
            '',
            $default,
            $cached
        );
    }

    public function toArray(): array
    {
        return [
            'ip' => $this->ip,
            'iso_code' => $this->isoCode,
            'country' => $this->country,
            'city' => $this->city,
            'state' => $this->state,
            'state_name' => $this->stateName,
            'postal_code' => $this->postalCode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timezone' => $this->timezone,
            'continent' => $this->continent,
            'currency' => $this->currency,
            'default' => $this->default,
            'cached' => $this->cached,
        ];
    }
}