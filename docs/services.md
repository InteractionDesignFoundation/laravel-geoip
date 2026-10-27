# Services

## MaxMind Database

⚠️ Before using MaxMind driver, you must install the `geoip2/geoip2` package using the Composer package manager.

The database location to use is specified in the config file in the "services" section under maxmind_database.
Along with the URL of where to download the database from when running the php artisan geoip:update.
Note: The `geoip:update` command will need to be run before the package will work.

```php
'service' => 'maxmind_database',
```

## IP-API

The free ip-api.com tier is **HTTP-only**: lookups are unencrypted and can be spoofed by anyone on the network path, so it is not suitable for production. Set `key` to use the HTTPS Pro endpoint. With `'secure' => true` and no key a warning is logged once when the service boots; set `'secure' => false` to acknowledge plain HTTP and silence it.

## Timeouts

All HTTP-based services and `maxmind_api` use the top-level `connect_timeout` (2 s) and `timeout` (5 s) values from `config/geoip.php`. Put `connect_timeout` or `timeout` inside a service's section to override them for that service.
