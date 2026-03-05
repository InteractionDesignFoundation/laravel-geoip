# GeoIP for Laravel

[![run-tests](https://github.com/InteractionDesignFoundation/laravel-geoip/actions/workflows/run-tests.yml/badge.svg)](https://github.com/InteractionDesignFoundation/laravel-geoip/actions/workflows/run-tests.yml)
[![Type coverage](https://shepherd.dev/github/InteractionDesignFoundation/laravel-geoip/coverage.svg)](https://shepherd.dev/github/InteractionDesignFoundation/laravel-geoip)
[![Psalm error level](https://shepherd.dev/github/InteractionDesignFoundation/laravel-geoip/level.svg)](https://shepherd.dev/github/InteractionDesignFoundation/laravel-geoip)

Determine the geographical location and currency of website visitors based on their IP addresses.

> Actively maintained fork of [`torann/geoip`](https://github.com/Torann/laravel-geoip) with modern PHP/Laravel support, better types, and additional features. [Migration guide](./docs/migration.md).

## Installation

```sh
composer require interaction-design-foundation/laravel-geoip
```

Publish the config file:

```sh
php artisan vendor:publish --provider="InteractionDesignFoundation\GeoIP\GeoIPServiceProvider" --tag=config
```

Set the `GEOIP_SERVICE` env variable to one of the [supported services](#services).

## Quick Start

Use the `geoip()` helper or the `GeoIP` facade:

```php
// Get location for an IP
$location = geoip('203.0.113.1');

// Get location for the current visitor
$location = geoip()->getLocation();

// Access location data
$location->city;       // "New Haven"
$location->country;    // "United States"
$location->iso_code;   // "US"
$location->timezone;   // "America/New_York"
$location->currency;   // "USD"
```

The `Location` object contains: `ip`, `iso_code`, `country`, `city`, `state`, `state_name`, `postal_code`, `lat`, `lon`, `timezone`, `continent`, `currency`, `default`, and `cached`.

It implements `ArrayAccess`, so both `$location->city` and `$location['city']` work.

## Services

Set the service via the `GEOIP_SERVICE` env variable or in `config/geoip.php`:

| Service | Key | Requires |
|---------|-----|----------|
| [MaxMind Database](https://www.maxmind.com/en/geoip2-databases) | `maxmind_database` | `geoip2/geoip2` package + license key |
| [MaxMind Web API](https://www.maxmind.com/en/geoip2-precision-services) | `maxmind_api` | `geoip2/geoip2` package + user ID & license key |
| [IP-API](https://ip-api.com/) | `ipapi` | API key (for HTTPS) |
| [IPGeolocation](https://ipgeolocation.io/) | `ipgeolocation` | API key |
| [IPData](https://ipdata.co/) | `ipdata` | API key |
| [IPFinder](https://ipfinder.io/) | `ipfinder` | API key |
| [IP2Location](https://www.ip2location.io/) | `ip2location` | API key |

For detailed service configuration, see [services documentation](./docs/services.md).

## Configuration

Key options in `config/geoip.php`:

### Caching

GeoIP caches lookups using Laravel's cache system to reduce API calls. Set the `cache` option to:

- `all` -- cache all lookups
- `some` -- cache only the current user's lookup
- `none` -- disable caching

You can also configure `cache_tags`, `cache_expires` (TTL in seconds), and `cache_prefix`.

### Currency Detection

When `include_currency` is enabled (default), the package resolves the visitor's currency from their country ISO code using the [league/iso3166](https://github.com/thephpleague/iso3166) package.

### Default Location

Configure a fallback location returned when a lookup fails or the IP is local/invalid.

## Artisan Commands

```sh
# Download/update the local GeoIP database (required for maxmind_database)
php artisan geoip:update

# Clear cached locations
php artisan geoip:clear
```

## Changelog

See [Releases](https://github.com/InteractionDesignFoundation/laravel-geoip/releases) for what has changed recently.

## Contributing

See [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

