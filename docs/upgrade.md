# Upgrade guide

## Upgrading to 5.0

### `getLocation()` and `geoip($ip)` throw on invalid IP addresses

When an IP is passed explicitly, `GeoIP::getLocation($ip)` (and the `geoip($ip)` helper) now throws `\InvalidArgumentException` if the value is not a syntactically valid IPv4/IPv6 address. Previously the default location was returned.

```php
geoip('http://evil.example/'); // InvalidArgumentException
geoip('example.com');          // InvalidArgumentException
```

Unchanged:

- Private and reserved addresses (`192.168.1.1`, `::1`, ...) still return the default location.
- `null` and `''` still fall back to the client IP.
- An invalid client IP (for example from a spoofed `X-Forwarded-For` header) never throws; the default location is returned.

If you pass untrusted input, validate it or catch `\InvalidArgumentException`.

`ServiceInterface::locate()` implementations bundled with the package that call HTTP APIs (IPApi, IPData, IPFinder, IPGeoLocation, IP2Location) also throw `\InvalidArgumentException` for non-IP input when called directly.
