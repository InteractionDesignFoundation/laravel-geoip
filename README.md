# GeoIP for Laravel

[![run-tests](https://github.com/InteractionDesignFoundation/laravel-geoip/actions/workflows/run-tests.yml/badge.svg)](https://github.com/InteractionDesignFoundation/laravel-geoip/actions/workflows/run-tests.yml)
[![Latest Stable Version](https://poser.pugx.org/interaction-design-foundation/laravel-geoip/v/stable.png)](https://packagist.org/packages/interaction-design-foundation/laravel-geoip)
[![Total Downloads](https://poser.pugx.org/interaction-design-foundation/laravel-geoip/downloads.png)](https://packagist.org/packages/interaction-design-foundation/laravel-geoip)

Determine the geographical location and currency of website visitors based on their IP addresses.


## About this fork

We have forked [Torann/laravel-geoip](https://github.com/Torann/laravel-geoip) as it’s almost not maintained anymore.
This fork works with modern PHP versions only (8.0+).
Also, as for any InteractionDesignFoundation project, we are going to improving code quality by using types, static analysers, tests and linters.
But don’t worry, we are using SemVer 2.0.


## Installation

From the command line run:

```sh
composer require interaction-design-foundation/laravel-geoip
```


### Publish the configurations

Run this on the command line from the root of your project:
```sh
php artisan vendor:publish --provider="InteractionDesignFoundation\GeoIP\GeoIPServiceProvider" --tag=config
```


## Configuration

Quick breakdown of the two main options in the configuration file.
To find out more simple open the `config/geoip.php` file.


### Service Configuration

To simplify and keep things clean, all third party composer packages, that are needed for a service, are installed separately.

For further configuration options checkout the services page.


### Caching Configuration

GeoIP uses Laravel’s default caching to store queried IP locations.
This is done to reduce the number of calls made to the selected service, as some of them are rate limited.

Options:
 - `all` all location are cached
 - `some` cache only the requesting user
 - `none` caching is completely disable


## Usage

There are few options to use the package:
 - `geoip()` helper function
 - `InteractionDesignFoundation\GeoIP\Facades\GeoIP` facade

```php
geoip()->getLocation('27.974.399.65'); // Get the location from the provided IP.
geoip()->getClientIP(); // Will return the user IP address.
```

Example of Location object:
```php
\InteractionDesignFoundation\GeoIP\Location {
[
        'ip'           => '1.1.1.1',
        'iso_code'     => 'US',
        'country'      => 'United States',
        'city'         => 'New Haven',
        'state'        => 'CT',
        'state_name'   => 'Connecticut',
        'postal_code'  => '06510',
        'lat'          => 41.28,
        'lon'          => -72.88,
        'timezone'     => 'America/New_York',
        'continent'    => 'NA',
        'currency'     => 'USD',
        'default'      => false,
    ]
}
```

`Location` class implements `\ArrayAccess` interface, means you can access properties of the `Location` object using array access syntax:
```php
$location = geoip()->getLocation();
$city = $location['city'];
```

### Artisan

Some services require downloading and use local database to detect Location by IP address.
There is a console command to download/update database:
```sh
php artisan geoip:update
```

Some cache drivers offer the ability to clear cached locations:
```sh
php artisan geoip:clear
```

### Changelog

Please see [Releases](https://github.com/InteractionDesignFoundation/laravel-geoip/releases) for more information on what has changed recently.


## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.


## Contributions

Many people have contributed to project since its inception.

Thanks to:

- [Daniel Stainback](https://github.com/Torann) (creator of the original package)
- [Dwight Watson](https://github.com/dwightwatson)
- [nikkiii](https://github.com/nikkiii)
- [jeffhennis](https://github.com/jeffhennis)
- [max-kovpak](https://github.com/max-kovpak)
- [dotpack](https://github.com/dotpack)
- [Jess Archer](https://github.com/jessarcher)
