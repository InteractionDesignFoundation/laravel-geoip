# GeoIP for Laravel

[![run-tests](https://github.com/InteractionDesignFoundation/laravel-geoip/actions/workflows/run-tests.yml/badge.svg)](https://github.com/InteractionDesignFoundation/laravel-geoip/actions/workflows/run-tests.yml)
[![Type coverage](https://shepherd.dev/github/InteractionDesignFoundation/laravel-geoip/coverage.svg)](https://shepherd.dev/github/InteractionDesignFoundation/laravel-geoip)
[![Psalm error level](https://shepherd.dev/github/InteractionDesignFoundation/laravel-geoip/level.svg)](https://shepherd.dev/github/InteractionDesignFoundation/laravel-geoip)

Determine the geographical location and currency of website visitors based on their IP addresses.


## About this fork

We have forked [`torann/geoip`](https://github.com/Torann/laravel-geoip) as it’s almost not actively maintained anymore.
This fork works with modern PHP versions only (8.0+), maintained and includes additional features:
 - Support modern Laravel and PHP versions
 - Better types (native and PHPDoc)
 - Safer file and network functionality
 - Ability to prefix cache keys (`cache_prefix` config option)
 - Ability to change service using `GEOIP_SERVICE` env var
 - Updated currencies
 - More predictable exceptions

But don’t worry, we are following SemVer 2.0. The [package migration is straightforward](./docs/migration.md).


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

and set the `GEOIP_SERVICE` env variable.

## Configuration

Quick breakdown of the two main options in the configuration file.
To find out more simple open the `config/geoip.php` file.


### Service Configuration

To simplify and keep things clean, all third party composer packages, which are needed for a service, are installed separately.

For further configuration options, checkout the [services page](./docs/services.md).


### Caching Configuration

GeoIP uses Laravel’s default caching to store queried IP locations.
This is done to reduce the number of calls made to the selected service, as some of them are rate limited.

Options:
 - `all` all locations are cached
 - `some` cache only the requesting user
 - `none` caching is completely disabled


## Usage

There are few options to use the package:
 - `geoip()` helper function
 - `InteractionDesignFoundation\GeoIP\Facades\GeoIP` facade

```php
geoip()->getLocation('27.974.399.65'); // Get the location from the provided IP.
geoip()->getClientIP(); // Will return the user IP address.
```

Example of a `Location` object:
```php
\InteractionDesignFoundation\GeoIP\Location {[
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
]}
```

`Location` class implements `\ArrayAccess` interface, means you can access properties of the `Location` object using both object and array access:
```php
$location = geoip()->getLocation();

$city = $location->city;
// The same as:
$city = $location['city'];
```

### Artisan

Some services require downloading and use a local database to detect Location by IP address.
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

Many people have contributed to the project since its inception.

Thanks to:

<!-- readme: contributors -start -->
<table>
	<tbody>
		<tr>
            <td align="center">
                <a href="https://github.com/alies-dev">
                    <img src="https://avatars.githubusercontent.com/u/5278175?v=4" width="100;" alt="alies-dev"/>
                    <br />
                    <sub><b>Alies Lapatsin</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/Torann">
                    <img src="https://avatars.githubusercontent.com/u/1406755?v=4" width="100;" alt="Torann"/>
                    <br />
                    <sub><b>Daniel Stainback</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/dotpack">
                    <img src="https://avatars.githubusercontent.com/u/1175814?v=4" width="100;" alt="dotpack"/>
                    <br />
                    <sub><b>Ilia Ermolin</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/lptn">
                    <img src="https://avatars.githubusercontent.com/u/150333538?v=4" width="100;" alt="lptn"/>
                    <br />
                    <sub><b>alies-lptn</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/dwightwatson">
                    <img src="https://avatars.githubusercontent.com/u/1100408?v=4" width="100;" alt="dwightwatson"/>
                    <br />
                    <sub><b>Dwight Watson</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/jessarcher">
                    <img src="https://avatars.githubusercontent.com/u/4977161?v=4" width="100;" alt="jessarcher"/>
                    <br />
                    <sub><b>Jess Archer</b></sub>
                </a>
            </td>
		</tr>
		<tr>
            <td align="center">
                <a href="https://github.com/highstrike">
                    <img src="https://avatars.githubusercontent.com/u/2379538?v=4" width="100;" alt="highstrike"/>
                    <br />
                    <sub><b>Flavius Cosmin</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/kyranb">
                    <img src="https://avatars.githubusercontent.com/u/5426926?v=4" width="100;" alt="kyranb"/>
                    <br />
                    <sub><b>Kyran</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/osmhub">
                    <img src="https://avatars.githubusercontent.com/u/13149318?v=4" width="100;" alt="osmhub"/>
                    <br />
                    <sub><b>osmhub</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/mithredate">
                    <img src="https://avatars.githubusercontent.com/u/6016632?v=4" width="100;" alt="mithredate"/>
                    <br />
                    <sub><b>Mehrdad</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/Pythagus">
                    <img src="https://avatars.githubusercontent.com/u/34168890?v=4" width="100;" alt="Pythagus"/>
                    <br />
                    <sub><b>Damien MOLINA</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/rjp2525">
                    <img src="https://avatars.githubusercontent.com/u/1334865?v=4" width="100;" alt="rjp2525"/>
                    <br />
                    <sub><b>Reno Philibert</b></sub>
                </a>
            </td>
		</tr>
		<tr>
            <td align="center">
                <a href="https://github.com/mikemand">
                    <img src="https://avatars.githubusercontent.com/u/745184?v=4" width="100;" alt="mikemand"/>
                    <br />
                    <sub><b>Micheal Mand</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/LukeT">
                    <img src="https://avatars.githubusercontent.com/u/2203091?v=4" width="100;" alt="LukeT"/>
                    <br />
                    <sub><b>Luke Thompson</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/janicerar">
                    <img src="https://avatars.githubusercontent.com/u/29040621?v=4" width="100;" alt="janicerar"/>
                    <br />
                    <sub><b>Jani Cerar</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/Butochnikov">
                    <img src="https://avatars.githubusercontent.com/u/4212297?v=4" width="100;" alt="Butochnikov"/>
                    <br />
                    <sub><b>Alexey</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/ncla">
                    <img src="https://avatars.githubusercontent.com/u/5507083?v=4" width="100;" alt="ncla"/>
                    <br />
                    <sub><b>ncla</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/acidjazz">
                    <img src="https://avatars.githubusercontent.com/u/967369?v=4" width="100;" alt="acidjazz"/>
                    <br />
                    <sub><b>kevin olson</b></sub>
                </a>
            </td>
		</tr>
		<tr>
            <td align="center">
                <a href="https://github.com/jalmatari">
                    <img src="https://avatars.githubusercontent.com/u/2941118?v=4" width="100;" alt="jalmatari"/>
                    <br />
                    <sub><b>Jamal Al-Matari</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/irtaza100">
                    <img src="https://avatars.githubusercontent.com/u/34660777?v=4" width="100;" alt="irtaza100"/>
                    <br />
                    <sub><b>irtaza100</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/evaldas-leliuga">
                    <img src="https://avatars.githubusercontent.com/u/1867113?v=4" width="100;" alt="evaldas-leliuga"/>
                    <br />
                    <sub><b>Evaldas Leliūga</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/rasoulrahimii">
                    <img src="https://avatars.githubusercontent.com/u/24825810?v=4" width="100;" alt="rasoulrahimii"/>
                    <br />
                    <sub><b>Rasoul Rahimii</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/PhoenixPeca">
                    <img src="https://avatars.githubusercontent.com/u/9730242?v=4" width="100;" alt="PhoenixPeca"/>
                    <br />
                    <sub><b>Phoenix Eve Aspacio</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/lloricode">
                    <img src="https://avatars.githubusercontent.com/u/8251344?v=4" width="100;" alt="lloricode"/>
                    <br />
                    <sub><b>Lloric Mayuga Garcia</b></sub>
                </a>
            </td>
		</tr>
		<tr>
            <td align="center">
                <a href="https://github.com/sakalauskas">
                    <img src="https://avatars.githubusercontent.com/u/1455148?v=4" width="100;" alt="sakalauskas"/>
                    <br />
                    <sub><b>Laurynas Sakalauskas</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/KelvynCarbone">
                    <img src="https://avatars.githubusercontent.com/u/5288360?v=4" width="100;" alt="KelvynCarbone"/>
                    <br />
                    <sub><b>Kelvyn Carbone</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/hughsaffar">
                    <img src="https://avatars.githubusercontent.com/u/10440022?v=4" width="100;" alt="hughsaffar"/>
                    <br />
                    <sub><b>Hugh Saffar</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/Dylan-DPC">
                    <img src="https://avatars.githubusercontent.com/u/99973273?v=4" width="100;" alt="Dylan-DPC"/>
                    <br />
                    <sub><b>Dylan DPC</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/faustbrian">
                    <img src="https://avatars.githubusercontent.com/u/22145591?v=4" width="100;" alt="faustbrian"/>
                    <br />
                    <sub><b>Brian Faust</b></sub>
                </a>
            </td>
            <td align="center">
                <a href="https://github.com/Omranic">
                    <img src="https://avatars.githubusercontent.com/u/406705?v=4" width="100;" alt="Omranic"/>
                    <br />
                    <sub><b>Abdelrahman Omran</b></sub>
                </a>
            </td>
		</tr>
	<tbody>
</table>
<!-- readme: contributors -end -->