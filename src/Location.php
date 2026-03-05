<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP;

use ArrayAccess;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

/**
 * Class Location
 *
 *
 * @property-read string|null $ip
 * @property-read string|null $iso_code
 * @property-read string|null $country
 * @property-read string|null $city
 * @property-read string|null $state
 * @property-read string|null $state_name
 * @property-read string|null $postal_code
 * @property-read float|null $lat
 * @property-read float|null $lon
 * @property-read string|null $timezone
 * @property-read string|null $continent
 * @property-read string|null $currency
 * @property-read bool $default
 * @property-read bool $cached
 * @property-read string $displayName {@see static::getDisplayNameAttribute()}
 *
 * @psalm-type LocationArray = array{
 *     ip: string,
 *     iso_code: string|null,
 *     country: string|null,
 *     city: string|null,
 *     state: string|null,
 *     state_name: string|null,
 *     postal_code: string|null,
 *     lat: float|null,
 *     lon: float|null,
 *     timezone: string|null,
 *     continent: string|null,
 *     currency?: string|null,
 *     default?: bool,
 *     cached?: bool,
 *     localizations?: array<string, string|null>,
 * }
 * How to use it: @@psalm-import-type LocationArray from \InteractionDesignFoundation\GeoIP\Location
 *
 * @template-implements \ArrayAccess<string, mixed>
 */
class Location implements ArrayAccess
{
    /**
     * Create a new location instance.
     *
     * @param array<string, mixed> $attributes
     * @psalm-param LocationArray $attributes
     */
    public function __construct(protected array $attributes = [])
    {
        $this->attributes = array_merge(
            ['default' => false, 'cached' => false],
            $this->attributes,
        );
    }

    /**
     * Determine if the location is for the same IP address.
     *
     * @param string $ip
     *
     * @return bool
     */
    public function same($ip): bool
    {
        return $this->getAttribute('ip') === $ip;
    }

    /** Return a new instance with the given attribute changed. */
    public function withAttribute(string $key, mixed $value): static
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;

        return $clone;
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute(string $key)
    {
        $value = Arr::get($this->attributes, $key);

        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set.
        $method = 'get' . Str::studly($key) . 'Attribute';
        if (method_exists($this, $method)) {
            return $this->{$method}($value);
        }

        return $value;
    }

    /** Return the display name of the location. */
    public function getDisplayNameAttribute(): ?string
    {
        return preg_replace('/^,\s/', '', sprintf('%s, %s', $this->city, $this->state));
    }

    /**
     * Is the location the default?
     *
     * @return bool
     */
    public function getDefaultAttribute(mixed $value): bool
    {
        return $value === true;
    }

    /**
     * Get the instance as an array.
     * @psalm-return LocationArray
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Get the location's attribute
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Determine if the given attribute exists.
     * @return bool
     */
    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->$offset);
    }

    /**
     * Get the value for a given offset.
     * @return mixed
     */
    #[\Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset;
    }

    /**
     * Set the value for a given offset.
     *
     * @throws \BadMethodCallException Always, as Location is immutable.
     */
    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('Location is immutable. Use withAttribute() instead.');
    }

    /**
     * Unset the value for a given offset.
     *
     * @throws \BadMethodCallException Always, as Location is immutable.
     */
    #[\Override]
    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('Location is immutable.');
    }

    /** Check if the location's attribute is set */
    public function __isset($key): bool
    {
        return array_key_exists($key, $this->attributes);
    }
}
