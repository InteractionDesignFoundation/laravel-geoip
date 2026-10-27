# Upgrading

## From 4.x to 5.0

Version 5.0 is a security and robustness release. Most apps only need the [required steps](#required-steps). Use the [behavior changes](#behavior-changes) to check code that depends on the details of lookups. If you extend the package, see [Custom services and subclasses](#custom-services-and-subclasses).

### Required steps

1. Update the package:

   ```bash
   composer require interaction-design-foundation/laravel-geoip:^5.0
   ```

2. If you published `config/geoip.php`, compare it with the package's `config/geoip.php` and add the new keys:

   ```php
   'connect_timeout' => 2,
   'timeout' => 5,
   'cache_not_found_expires' => null, // null = use cache_expires

   'services' => [
       'maxmind_database' => [
           // ...
           'account_id' => env('MAXMIND_ACCOUNT_ID'),
           'license_key' => env('MAXMIND_LICENSE_KEY'),
           'edition_id' => 'GeoLite2-City',
       ],
   ],
   ```

   Without these keys, the timeouts default to 2 s / 5 s and the not-found TTL falls back to `cache_expires`. Note that adding the `env(...)` lines immediately picks up any `MAXMIND_ACCOUNT_ID` / `MAXMIND_LICENSE_KEY` already set in your environment.

3. If you use `maxmind_database`:

   - Set `MAXMIND_ACCOUNT_ID` (shown in your MaxMind account). Updates then authenticate with HTTP Basic auth, and the license key is never part of the URL. Without an account ID, the package keeps using the legacy URL with the license key in the query string.
   - A published config from 4.x contains `'update_url' => sprintf('https://download.maxmind.com/app/geoip_download?...&license_key=%s...')`. A non-empty `update_url` takes precedence over `account_id`. To switch to Basic auth, replace that stock line with the keys above, using the same edition as in the old URL for `edition_id`. Keep `update_url` only for a deliberate custom endpoint (see [`geoip:update`](#geoipupdate)), and set `edition_id` only to the edition that endpoint actually serves.
   - Make sure the database file exists before the app serves lookups. Either run `php artisan geoip:update` during build or deploy, or keep a valid database file at `database_path` across deploys. The package no longer creates one for you (see [Missing MaxMind database](#missing-maxmind-database)).
   - **Run `php artisan geoip:update` once after upgrading.** 4.x copied MaxMind's fake test database to `database_path` when the file was missing, and 5.0 keeps using whatever file is there. If `geoip:update` never succeeded on a host, that host may still be serving test data.
   - **If `geoip:update` ever failed on 4.x, rotate your MaxMind license key.** Those error messages contained the download URL with the key, and may have reached logs, cron mail or error trackers.

### Behavior changes

#### Explicit invalid IPs throw `InvalidArgumentException`

`GeoIP::getLocation($ip)` and `geoip($ip)` throw `\InvalidArgumentException` when an explicitly passed `$ip` is not a syntactically valid IPv4/IPv6 address. Previously the default location was returned.

```php
geoip('http://evil.example/'); // InvalidArgumentException
geoip('example.com');          // InvalidArgumentException
```

Unchanged:

- Private and reserved addresses (`192.168.1.1`, `::1`, ...) still return the default location.
- `getLocation()`, `getLocation(null)`, `getLocation('')` and `geoip('')` look up the client IP. `geoip()` without arguments returns the `GeoIP` instance.
- An invalid client IP (for example from a spoofed `X-Forwarded-For` header) never throws; the default location is returned.

If you pass untrusted input, validate it before the call: `is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false`. Non-string input can also cause a `TypeError`. Avoid catching `\InvalidArgumentException` as a substitute: `getLocation()` calls `getService()`, which throws the same exception when no service or an invalid one is configured, so a catch would hide a configuration error.

The bundled HTTP services (IPApi, IPData, IPFinder, IPGeoLocation, IP2Location) also throw `\InvalidArgumentException` from `locate()` for non-IP input when called directly.

#### Missing MaxMind database

4.x silently copied the bundled MaxMind *test* database (fake data from 2016) to `database_path` when the file was missing. 5.0 doesn't do that. `MaxMindDatabase::locate()` throws `\RuntimeException` (`MaxMind database not found at <path>. Run "php artisan geoip:update".`). `getLocation()` catches it and returns the default location, logging an error when `log_failures` is enabled.

The database is opened lazily, so `geoip:update` works on a fresh install. If your app's tests relied on the auto-copied file, provide a database fixture for them.

#### `geoip:update`

- Downloads always use cURL over HTTPS (including redirects), with a 10 s connect and a 300 s total timeout.
- The archive's SHA-256 is verified. For a custom `update_url`, it must contain the query parameter `suffix=tar.gz`, and the same URL with `suffix=tar.gz.sha256` must serve the checksum. The package adds no credentials to a custom URL.
- The database must open as an MMDB file. Its type must match the edition: for the default endpoint that is `edition_id`; for a custom URL, an `edition_id` query parameter in the URL wins over the config value, and with neither the type isn't checked.
- The new file is written to a temporary file in the same directory as `database_path` and renamed over the old one. The process needs write access to that directory. On any failure the existing database stays untouched.
- Exceptions from the update are caught, printed (with the license key removed) and turn into a failure exit code. Scripts and scheduled tasks that run the command should check it. The command reports success only when the service's `update()` returns a non-empty string, and it reports success without doing anything for a service that has no `update()` method.

#### ip-api free tier

The free ip-api.com tier is HTTP-only, so requests and responses are unencrypted. HTTPS needs a paid key (`IPAPI_KEY`) together with `'secure' => true`. With `'secure' => true` (the default) and no key, the service still uses HTTP but logs a warning. Set `'secure' => false` to acknowledge plain HTTP and silence it.

#### Timeouts and provider errors

- The five HTTP services and `maxmind_api` default to a 2 s connect and a 5 s total timeout (previously 20 s / 90 s, and no limit for `maxmind_api`). Override them globally (`connect_timeout`, `timeout`) or per service with the same keys; a per-service value wins over the global one. Values are seconds; fractions are allowed. A zero, negative or non-numeric value falls back to the built-in 2 s / 5 s, not to the global value.
- A non-2xx response from an HTTP service throws `\RuntimeException`. IPData, IPFinder and IPGeoLocation throw `\Exception` for bodies that are not valid JSON or decode to a scalar, but they don't validate the fields of a decoded object or array. Responses larger than 1 MiB are rejected when the server announces their size.
- `getLocation()` catches these failures and returns the default location (logged as an error when `log_failures` is enabled). Direct `locate()` calls see the exception.

#### Non-global addresses are not looked up

`getLocation()` skips the provider for any address that isn't globally routable (PHP's `FILTER_FLAG_GLOBAL_RANGE`). Besides private and reserved ranges, this now includes CGNAT (`100.64.0.0/10`), documentation ranges (`192.0.2.0/24`, `2001:db8::/32`, ...) and 6to4 (`2002::/16`). On a cache miss, these addresses get the default location; results cached by 4.x are served until they expire. Direct `locate()` calls don't apply this filter.

IPv4-mapped IPv6 addresses (`::ffff:8.8.8.8`, `::ffff:808:808`) are converted to IPv4 before validation, lookup and caching.

#### "Address not found" results

When MaxMind reports that an address isn't in its data (`GeoIp2\Exception\AddressNotFoundException`):

- `getLocation()` returns the default location with `ip` set to the looked-up address.
- The result is logged at `debug` level (when `log_failures` is enabled), not as an error.
- The result is cached for `cache_not_found_expires` seconds (falls back to `cache_expires`), following the `cache` mode (`all`, `some`, `none`). The first result has `default` = `true` and `cached` = `false`; later cache hits have both `true`.

Other failures (timeouts, HTTP errors) are still not cached.

### Custom services and subclasses

- Services are still created with `new $class($config)`. The config now also contains the merged `connect_timeout` and `timeout` values.
- Services that implement `Psr\Log\LoggerAwareInterface` receive the package logger through `setLogger()` right after construction. The logger isn't available yet in `boot()`.
- `AbstractService` has new protected methods: `assertValidIp()`, `timeoutConfig()` and `ensureSuccessfulResponse()`. Rename any method with the same name in your subclass.
- `HttpClient` has a new protected `curlOptions()` and a public static `resolveTimeout()`.
- `MaxMindDatabase` subclasses:
  - `downloadFileByUrl()` is now `downloadFileByUrl(string $filename, string $url, ?string $userPwd = null, ?int $maxBytes = null): void`.
  - The transfer itself runs in a new protected `curlTransfer(array $options): ?array`.
  - `$reader` is `?Reader` and stays `null` until the first lookup.
  - `findDatabaseFile()` was removed.
- Custom services with an `update()` method: `geoip:update` treats only a non-empty string return value as success.
- `Cache::set()` accepts an optional third argument: the TTL in seconds.

### Upgrading with an AI coding agent

<details>
<summary>Prompt</summary>

```text
Upgrade interaction-design-foundation/laravel-geoip from 4.x to 5.0 in this Laravel application.
This prompt comes from the package's UPGRADING.md. After step 1, read
vendor/interaction-design-foundation/laravel-geoip/UPGRADING.md and follow it where it is more detailed.
Composer may change composer.json and composer.lock. Otherwise edit only application-owned files
(config/geoip.php, .env.example, deploy/CI scripts, app code, tests); never edit files in vendor/.
Report every change, and list decisions that need a human instead of guessing.

1. Run: composer require interaction-design-foundation/laravel-geoip:^5.0
2. Find usages: geoip(, GeoIP::, ->getLocation(, ->locate(, getService(, InteractionDesignFoundation\GeoIP.
3. Explicit IP lookups (geoip($ip), getLocation($ip), direct ->locate($ip)): a non-IP string now
   throws \InvalidArgumentException. If $ip can come from user input, request data, headers,
   queues or the database, validate it before the call:
   is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false.
   Do not add try/catch for \InvalidArgumentException instead: the same exception signals a
   misconfigured service. Calls without an IP, with null or with '' are unchanged.
4. If config/geoip.php is published, add connect_timeout, timeout and cache_not_found_expires
   as in the package config. Keep the app's existing values.
5. maxmind_database only:
   - If update_url is the stock 4.x expression (sprintf of
     https://download.maxmind.com/app/geoip_download?...license_key=%s...), replace it with
     account_id, license_key and edition_id as in the package config, using the edition from the
     old URL. Any other update_url is a custom endpoint: keep it, do not add edition_id unless the
     human confirms which edition it serves, and ask whether it serves the matching
     suffix=tar.gz.sha256 checksum over HTTPS.
   - Add MAXMIND_ACCOUNT_ID to .env.example and ask the human to set it in every environment.
   - Make sure php artisan geoip:update runs during build/deploy, or that a valid database is
     kept at database_path. Check that scripts calling geoip:update handle a non-zero exit code.
   - Tell the human to run php artisan geoip:update once on every host after upgrading: 4.x may
     have left MaxMind's fake test database at database_path.
   - If tests relied on the bundled database being auto-copied, give them a fixture.
   - Tell the human to rotate the MaxMind license key if geoip:update ever failed on 4.x.
6. ipapi without a key: do not change 'secure' on your own. Ask the human to choose between a
   paid key (HTTPS) and 'secure' => false (accept plain HTTP).
7. Check code that depends on lookup details and change it only where the new behavior breaks
   it: non-global addresses (CGNAT, documentation ranges, 6to4) now get the default location;
   providers time out after 2 s connect / 5 s total; HTTP errors fall back to the default
   location; MaxMind "address not found" results are cached and carry the looked-up IP;
   ::ffff:a.b.c.d is converted to IPv4.
8. Custom services or subclasses: check them against "Custom services and subclasses" in
   UPGRADING.md (new protected methods, MaxMindDatabase::downloadFileByUrl() signature,
   nullable $reader, removed findDatabaseFile(), update() must return a non-empty string).
9. Run the test suite and static analysis. Add tests only where the app's own behavior changed.
```

</details>
