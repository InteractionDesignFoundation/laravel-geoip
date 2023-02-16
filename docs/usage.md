# Usage

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
