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
        public readonly float $lat,
        public readonly float $lon,
        public readonly string $timezone,
        public readonly string $continent,
        public string $currency,
        public bool $default,
        public bool $cached,
    ) {
    }

    public function setCached(bool $cached): self
    {
        $this->cached = $cached;
        return $this;
    }

    public function setDefault(bool $default): self
    {
        $this->default = $default;
        return $this;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @param  array{
     *     ip: string,
     *     iso_code: string,
     *     country: string,
     *     city: string,
     *     state: string,
     *     state_name: string,
     *     postal_code: string,
     *     lat: float,
     *     lon: float,
     *     timezone: string,
     *     continent: string,
     *     currency: string,
     *     default: bool,
     *     cached: bool,
     * }  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            ip: (string) $attributes['ip'],
            isoCode: (string) $attributes['iso_code'],
            country: (string) $attributes['country'],
            city: (string) $attributes['city'],
            state: (string) $attributes['state'],
            stateName: (string) $attributes['state_name'],
            postalCode: (string) $attributes['postal_code'],
            lat: (float) $attributes['lat'],
            lon: (float) $attributes['lon'],
            timezone: (string) $attributes['timezone'],
            continent: (string) $attributes['continent'],
            currency: (string) $attributes['currency'],
            default: (bool) $attributes['default'],
            cached: (bool) $attributes['cached'],
        );
    }

    /** @return array<string, string|float|bool> */
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
            'lat' => $this->lat,
            'lon' => $this->lon,
            'timezone' => $this->timezone,
            'continent' => $this->continent,
            'currency' => $this->currency,
            'default' => $this->default,
            'cached' => $this->cached,
        ];
    }
}