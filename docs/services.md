# Services

## MaxMind Database

⚠️ Before using MaxMind driver, you must install the `geoip2/geoip2` package using the Composer package manager.

The database location to use is specified in the config file in the "services" section under maxmind_database.

`php artisan geoip:update` downloads the database. It authenticates against MaxMind's download permalink with HTTP Basic auth, so set both credentials from your MaxMind account:

```dotenv
MAXMIND_ACCOUNT_ID=123456
MAXMIND_LICENSE_KEY=your-license-key
```

Use `edition_id` (default `GeoLite2-City`) to pick the edition; the downloaded database must be of that type. If `account_id` is empty, or you set a custom `update_url` (it must contain `suffix=tar.gz`), the legacy URL with the license key in the query string is used instead. The update always verifies the archive's SHA-256 checksum and the database edition, and swaps the file atomically; on any failure the existing database is kept and the license key is never included in error output.

Note: `geoip:update` must be run before lookups work. Until then the package does not fall back to any bundled database; lookups return the default location and the failure is logged.

If `geoip:update` failed in an earlier version of this package, the error output may have contained your license key (as part of the download URL). Rotate the license key in your MaxMind account.

```php
'service' => 'maxmind_database',
```
