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

   Without these keys, the timeouts default to 2 s / 5 s and the not-found TTL falls back to `cache_expires`. The `env(...)` lines pick up any `MAXMIND_ACCOUNT_ID` / `MAXMIND_LICENSE_KEY` already set in your environment. If you cache the config (`php artisan config:cache`), rebuild the cache after changing `config/geoip.php` or `.env`.

3. If you pass IPs to `geoip($ip)` or `getLocation($ip)` from request data, headers, queues or the database, validate them before the call. A non-IP string now throws (see [Explicit invalid IPs throw](#explicit-invalid-ips-throw-invalidargumentexception)).

4. If you use `maxmind_database`:

   - Set `MAXMIND_ACCOUNT_ID` (shown in your MaxMind account). Updates then authenticate with HTTP Basic auth, and the license key is never part of the URL. Without an account ID, the package keeps using the legacy URL with the license key in the query string.
   - A published config from 4.x contains `'update_url' => sprintf('https://download.maxmind.com/app/geoip_download?...&license_key=%s...')`. A non-empty `update_url` takes precedence over `account_id`. To switch to Basic auth, replace that stock line with the keys above, using the same edition as in the old URL for `edition_id`. Keep `update_url` only for a deliberate custom endpoint (see [`geoip:update`](#geoipupdate)), and set `edition_id` only to the edition that endpoint actually serves.
   - `edition_id` must be a City edition (`GeoLite2-City`, `GeoIP2-City`). Lookups read the database with `city()`, so `geoip:update` succeeds with a Country edition but every lookup then fails.
   - Make sure the database file exists before the app serves lookups. Either run `php artisan geoip:update` during build or deploy, or keep a valid database file at `database_path` across deploys. The package no longer creates one for you (see [Missing MaxMind database](#missing-maxmind-database)).
   - **Run `php artisan geoip:update` once after upgrading.** 4.x copied MaxMind's fake test database to `database_path` when the file was missing, and 5.0 keeps using whatever file is there. If `geoip:update` never succeeded on a host, that host may still be serving test data.
   - **If `geoip:update` failed on 4.x, consider rotating your MaxMind license key.** Some 4.x download errors contained the download URL with the key, and may have reached logs, cron mail or error trackers.

### Behavior changes

#### Explicit invalid IPs throw `InvalidArgumentException`

`GeoIP::getLocation($ip)` and `geoip($ip)` throw `\InvalidArgumentException` when an explicitly passed `$ip` is not a syntactically valid IPv4/IPv6 address. Previously the default location was returned, and `'0'` looked up the client IP.

```php
geoip('http://evil.example/'); // InvalidArgumentException
geoip('example.com');          // InvalidArgumentException
```

Unchanged:

- Private and reserved addresses (`192.168.1.1`, `::1`, ...) still return the default location.
- `getLocation()`, `getLocation(null)`, `getLocation('')` and `geoip('')` look up the client IP. `geoip()` without arguments returns the `GeoIP` instance.
- A client IP that isn't a valid address never throws; the default location is returned.

If `$ip` can come from untrusted input, validate it before the call: `is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false`. Non-string input can also cause a `TypeError`. Don't fall back to `geoip(null)` for invalid input: it returns the `GeoIP` instance, not a `Location`. Avoid catching `\InvalidArgumentException` as a substitute: `getLocation()` calls `getService()`, which throws the same exception when no service or an invalid one is configured, so a catch would hide a configuration error.

The bundled HTTP services (IPApi, IPData, IPFinder, IPGeoLocation, IP2Location) also throw `\InvalidArgumentException` from `locate()` for any non-IP string, including `''`, when called directly. `locate()` has no client-IP fallback.

#### Missing MaxMind database

4.x silently copied the bundled MaxMind *test* database (fake data from 2016) to `database_path` when the file was missing. 5.0 doesn't do that. `MaxMindDatabase::locate()` throws `\RuntimeException` (`MaxMind database not found at <path>. Run "php artisan geoip:update".`). `getLocation()` catches it and returns the default location, logging an error when `log_failures` is enabled.

The database is opened lazily, so `geoip:update` works on a fresh install. If your app's tests relied on the auto-copied file, provide a database fixture for them.

#### `geoip:update`

- Each download (checksum, then archive) uses cURL over HTTPS only, including redirects, with a 10 s connect and a 300 s total timeout.
- The archive's SHA-256 is verified. A custom `update_url` must contain the literal (not URL-encoded) query parameter `suffix=tar.gz`, and the same URL with `suffix=tar.gz.sha256` must serve a file that starts with the hex digest. The package adds no credentials to a custom URL.
- The database must open as an MMDB file. Its type must match the edition: for the default endpoint that is `edition_id`; for a custom URL, an `edition_id` query parameter in the URL wins over the config value, and with neither the type isn't checked.
- The new file is written to a temporary file in the same directory as `database_path`, validated and renamed over the old one. The process needs write access to that directory. If the download, checksum or validation fails, the existing database stays untouched.
- If `database_path` is a symlink, the link is kept and its target is replaced atomically. The target's directory must be writable, because the temporary file is created there. (5.0.0 replaced the link itself with a regular file; fixed in 5.0.1.)
- The command catches `\Exception` from `update()`, prints its message and exits with a failure code. The bundled MaxMind updater removes the license key from its messages; custom services must sanitize their own. Errors while resolving the service (a missing config key, no `service` configured) are printed the same way and also exit with a failure code (5.0.0 let some of them escape as uncaught exceptions). Scripts and scheduled tasks that run the command should check the exit code.
- The command updates the configured `service`. It reports success only when the service's `update()` returns a non-empty string, and it reports success without doing anything for a service that has no `update()` method.

#### ip-api free tier

The free ip-api.com tier is HTTP-only, so requests and responses are unencrypted. HTTPS needs a paid key (`IPAPI_KEY`). With a key, requests always go to `https://pro.ip-api.com/` and `'secure'` is ignored (before 5.1.0, `'secure' => false` sent the key over plain HTTP). With `'secure' => true` (the default) and no key, the service still uses HTTP and logs a warning at most once a day when the default cache store is shared and persistent (e.g. redis, file, database); with the array or null store it is logged per request, as before 5.1.0. Set `'secure' => false` to acknowledge plain HTTP and silence it.

#### Timeouts and provider errors

- The five HTTP services and `maxmind_api` default to a 2 s connect and a 5 s total timeout. Previously the five HTTP services used 20 s / 90 s, and `maxmind_api` had no total limit. Override them globally (`connect_timeout`, `timeout`) or per service with the same keys; a per-service value wins over the global one. Values are seconds; fractions are allowed. A `null`, zero, negative, non-numeric, non-finite (`INF`, `'1e309'`) or too large value falls back to the built-in 2 s / 5 s, not to the global value.
- A non-2xx response from an HTTP service throws `\RuntimeException`. IPData, IPFinder and IPGeoLocation throw `\Exception` for bodies that are not valid JSON or decode to a scalar, but they don't validate the fields of a decoded object or array. The five HTTP services reject responses larger than 1 MiB. With libcurl older than 8.4, this only works when the server announces the size.
- `getLocation()` catches these failures and returns the default location (logged as an error when `log_failures` is enabled). Direct `locate()` calls see the exception.

#### Non-global addresses are not looked up

`getLocation()` skips the provider for any address that isn't globally routable (PHP's `FILTER_FLAG_GLOBAL_RANGE`; the exact ranges depend on the PHP version). 4.x skipped only private and reserved IPv4 and private IPv6 addresses, so IPv6 loopback (`::1`), link-local (`fe80::/10`) and other reserved IPv6 addresses reached the provider. The filter now also covers CGNAT (`100.64.0.0/10`), documentation ranges (`192.0.2.0/24`, `2001:db8::/32`, ...) and 6to4 (`2002::/16`). On a cache miss, these addresses get the default location; results cached by 4.x are served until they expire. Direct `locate()` calls don't apply this filter.

IPv4-mapped IPv6 addresses (`::ffff:8.8.8.8`, `::ffff:808:808`) are converted to IPv4 before validation, lookup and caching.

#### "Address not found" results

When MaxMind reports that an address isn't in its data (`GeoIp2\Exception\AddressNotFoundException`):

- `getLocation()` returns the default location with `ip` set to the looked-up address.
- The result is logged at `debug` level (when `log_failures` is enabled), not as an error.
- The result is cached for `cache_not_found_expires` seconds (falls back to `cache_expires`), following the `cache` mode (`all`, `some`, `none`). With the stock `default_location`, the first result has `default` = `true` and `cached` = `false`, and later cache hits have both set to `true`.

Other failures (timeouts, HTTP errors) are still not cached.

### Custom services and subclasses

- Services are still created with `new $class($config)`. The config now also contains the merged `connect_timeout` and `timeout` values.
- Services that implement `Psr\Log\LoggerAwareInterface` receive the package logger through `setLogger()` right after construction. The constructor calls `boot()`, so the logger isn't available there yet. `IPApi` now implements this interface.
- `AbstractService` has new protected methods: `assertValidIp()`, `timeoutConfig()` and `ensureSuccessfulResponse()`. Check your subclass for methods with the same names: rename unrelated ones and adapt deliberate overrides to the new signatures.
- `HttpClient` has a new protected `curlOptions()`, a public static `resolveTimeout()` and the public `float` constants `DEFAULT_CONNECT_TIMEOUT` and `DEFAULT_TIMEOUT`. A subclass that redeclares those constants must type them as `float`.
- `MaxMindDatabase` subclasses:
  - `downloadFileByUrl()` is now `downloadFileByUrl(string $filename, string $url, ?string $userPwd = null, ?int $maxBytes = null): void`.
  - The transfer itself runs in a new protected `curlTransfer(array $options): ?array`.
  - `boot()` no longer opens the database. `$reader` is typed `?Reader`. It stays `null` until the base `locate()` opens it, and a successful `update()` resets it to `null`. A subclass that overrides `locate()` must get the reader from the protected `$this->reader()` (since 5.0.1), which opens it on first use, instead of reading `$this->reader` directly. On 5.0.0, where `reader()` is private, open it yourself: `$this->reader ??= new Reader($this->config('database_path'), $this->config('locales', ['en']));`. A redeclared `$reader` property must be typed `?Reader`.
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
3. Explicit IP lookups (geoip($ip), getLocation($ip)): a non-IP string now throws
   \InvalidArgumentException. If $ip can come from user input, request data, headers,
   queues or the database, validate it before the call:
   is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false.
   Do not add try/catch for \InvalidArgumentException instead: the same exception signals a
   misconfigured service. geoip() and getLocation() calls without an IP, with null or with ''
   are unchanged; leave them as they are. Do not use geoip(null) as the fallback for invalid
   input: it returns the GeoIP instance, not a Location. Direct ->locate($ip) calls on the
   bundled HTTP services need a valid IP string; '' throws there.
4. If config/geoip.php is published, add connect_timeout, timeout and cache_not_found_expires
   as in the package config. Keep the app's existing values. If the app caches its config
   (php artisan config:cache), make sure deploys rebuild the cache.
5. maxmind_database only:
   - If update_url is the stock 4.x expression (sprintf of
     https://download.maxmind.com/app/geoip_download?...license_key=%s...), replace it with
     account_id, license_key and edition_id as in the package config, using the edition from the
     old URL. edition_id must be a City edition (GeoLite2-City, GeoIP2-City). Any other
     update_url is a custom endpoint: keep it, do not add edition_id unless the human confirms
     which edition it serves, and ask whether it contains the literal query parameter
     suffix=tar.gz and serves the matching suffix=tar.gz.sha256 checksum over HTTPS.
   - If the app uses the default MaxMind endpoint (no custom update_url), add MAXMIND_ACCOUNT_ID
     to .env.example and ask the human to set it in every environment.
   - Make sure php artisan geoip:update runs during build/deploy, or that a valid database is
     kept at database_path. Check that scripts calling geoip:update handle a non-zero exit code.
   - Tell the human to run php artisan geoip:update once on every host after upgrading: 4.x may
     have left MaxMind's fake test database at database_path.
   - If tests relied on the bundled database being auto-copied, give them a fixture.
   - Tell the human to consider rotating the MaxMind license key if geoip:update failed on 4.x.
6. ipapi without a key: do not change 'secure' on your own. Ask the human to choose between a
   paid key (HTTPS) and 'secure' => false (accept plain HTTP). With a key, 'secure' has no effect.
7. Check code that depends on lookup details and change it only where the new behavior breaks
   it: non-global addresses (CGNAT, documentation ranges, 6to4) now get the default location;
   providers time out after 2 s connect / 5 s total; HTTP errors fall back to the default
   location; MaxMind "address not found" results are cached and carry the looked-up IP;
   ::ffff:a.b.c.d is converted to IPv4.
8. Custom services or subclasses: check them against "Custom services and subclasses" in
   UPGRADING.md (new protected methods and HttpClient constants,
   MaxMindDatabase::downloadFileByUrl() signature, $reader no longer opened in boot() (use $this->reader() in an overridden locate()),
   removed findDatabaseFile(), update() must return a non-empty string).
9. Run the test suite and static analysis. Add tests only where the app's own behavior changed.
```

</details>
