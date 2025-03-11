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
 * @property string|null $ip
 * @property string|null $iso_code
 * @property string|null $country
 * @property string|null $city
 * @property string|null $state
 * @property string|null $state_name
 * @property string|null $postal_code
 * @property float|null $lat
 * @property float|null $lon
 * @property string|null $timezone
 * @property string|null $continent
 * @property string|null $currency
 * @property bool $default
 * @property bool $cached
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

    /**
     * Set a given attribute on the location.
     *
     * @param string $key
     *
     * @return $this
     */
    public function setAttribute($key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
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
    public function getDefaultAttribute($value): bool
    {
        return is_null($value) ? false : $value;
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
     * Set the location's attribute
     * @param string $key
     */
    public function __set(string $key, mixed $value)
    {
        $this->setAttribute($key, $value);
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
     * @return void
     */
    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->$offset = $value;
    }

    /**
     * Unset the value for a given offset.
     * @return void
     */
    #[\Override]
    public function offsetUnset(mixed $offset): void
    {
        unset($this->$offset);
    }

    /** Check if the location's attribute is set */
    public function __isset($key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /** Unset an attribute on the location. */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }
}
