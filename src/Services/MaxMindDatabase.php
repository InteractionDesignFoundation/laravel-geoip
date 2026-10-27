<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use GeoIp2\Database\Reader;
use GeoIp2\Model\City;

/**
 * @psalm-api
 */
class MaxMindDatabase extends AbstractService
{
    private const string DOWNLOAD_BASE_URL = 'https://download.maxmind.com';

    private const int MAX_CHECKSUM_BYTES = 1024;

    /** Opened lazily so that `geoip:update` can bootstrap the service on a fresh install. */
    protected ?Reader $reader = null;

    #[\Override]
    public function boot(): void
    {
        $this->ensureConfigurationParameterDefined('database_path');
    }

    /** {@inheritDoc} */
    #[\Override]
    public function locate($ip): \InteractionDesignFoundation\GeoIP\Location
    {
        $record = $this->reader()->city($ip);

        return $this->hydrate([
            'ip' => $ip,
            'iso_code' => $record->country->isoCode,
            'country' => $record->country->name,
            'city' => $record->city->name,
            'state' => $record->mostSpecificSubdivision->isoCode,
            'state_name' => $record->mostSpecificSubdivision->name,
            'postal_code' => $record->postal->code,
            'lat' => $record->location->latitude,
            'lon' => $record->location->longitude,
            'timezone' => $record->location->timeZone,
            'continent' => $record->continent->code,
            'localizations' => $this->getLocalizations($record),
        ]);
    }

    /**
     * Download, verify and atomically install the latest database.
     *
     * @throws \Exception
     */
    public function update(): string
    {
        $databasePath = $this->config('database_path');
        assert(is_string($databasePath) && $databasePath !== '', 'Invalid "database_path" config value');

        ['archive' => $archiveUrl, 'checksum' => $checksumUrl, 'user_pwd' => $userPwd, 'edition' => $edition] = $this->resolveDownload();

        try {
            $this->withTemporaryDirectory(function (string $directory) use ($archiveUrl, $checksumUrl, $userPwd, $edition, $databasePath): void {
                $archive = $directory.'/maxmind.tar.gz';
                $checksum = $directory.'/maxmind.tar.gz.sha256';

                $this->downloadFileByUrl($checksum, $checksumUrl, $userPwd, self::MAX_CHECKSUM_BYTES);
                $this->downloadFileByUrl($archive, $archiveUrl, $userPwd);
                $this->assertChecksumMatches($archive, $checksum);

                $this->installDatabase($archive, $databasePath, $edition);
            });
        } catch (\Exception $exception) {
            // Single exit point: nothing that escapes update() may carry the key, whichever step produced it.
            $message = $this->redact($exception->getMessage(), $archiveUrl, $userPwd);

            throw $message === $exception->getMessage() ? $exception : new \RuntimeException($message);
        }

        $this->reader?->close();
        $this->reader = null;

        return sprintf('Database file (%s) updated.', $databasePath);
    }

    /** @return array{archive: string, checksum: string, user_pwd: string|null, edition: string|null} */
    private function resolveDownload(): array
    {
        /** @var mixed $updateUrl */
        $updateUrl = $this->config('update_url');
        /** @var mixed $rawAccountId */
        $rawAccountId = $this->config('account_id');
        $accountId = is_int($rawAccountId) ? (string) $rawAccountId : $rawAccountId;

        if (is_string($updateUrl) && $updateUrl !== '') {
            // Explicit URL: the caller owns authentication, so no credentials are sent to a host we did not pick.
            $query = [];
            parse_str((string) parse_url($updateUrl, \PHP_URL_QUERY), $query);
            /** @var mixed $configuredEdition */
            $configuredEdition = $this->config('edition_id');

            return [
                'archive' => $updateUrl,
                'checksum' => $this->checksumUrl($updateUrl),
                'user_pwd' => null,
                'edition' => match (true) {
                    isset($query['edition_id']) && is_string($query['edition_id']) => $query['edition_id'],
                    is_string($configuredEdition) && $configuredEdition !== '' => $configuredEdition,
                    default => null,
                },
            ];
        }

        $this->ensureConfigurationParameterDefined('license_key');
        $licenseKey = (string) $this->config('license_key');
        $edition = (string) $this->config('edition_id', 'GeoLite2-City');

        if (! is_string($accountId) || $accountId === '') {
            $url = sprintf(
                '%s/app/geoip_download?edition_id=%s&license_key=%s&suffix=tar.gz',
                self::DOWNLOAD_BASE_URL,
                rawurlencode($edition),
                rawurlencode($licenseKey),
            );

            return ['archive' => $url, 'checksum' => $this->checksumUrl($url), 'user_pwd' => null, 'edition' => $edition];
        }

        $url = sprintf('%s/geoip/databases/%s/download?suffix=tar.gz', self::DOWNLOAD_BASE_URL, rawurlencode($edition));

        return ['archive' => $url, 'checksum' => $this->checksumUrl($url), 'user_pwd' => $accountId.':'.$licenseKey, 'edition' => $edition];
    }

    private function checksumUrl(#[\SensitiveParameter] string $archiveUrl): string
    {
        $checksumUrl = preg_replace('/(?<=[?&])suffix=tar\.gz(?=&|$)/', 'suffix=tar.gz.sha256', $archiveUrl, 1, $count);

        if ($checksumUrl === null || $count !== 1) {
            throw new \RuntimeException('The MaxMind update URL must request "suffix=tar.gz" so that the archive checksum can be verified.');
        }

        return $checksumUrl;
    }

    private function assertChecksumMatches(string $archive, string $checksumFile): void
    {
        // Bounded read: the checksum file is attacker-influenced input.
        $contents = (string) file_get_contents($checksumFile, false, null, 0, self::MAX_CHECKSUM_BYTES);
        $actual = hash_file('sha256', $archive);

        if ($actual === false || preg_match('/^\s*([0-9a-fA-F]{64})(?:\s|$)/', $contents, $match) !== 1 || ! hash_equals(strtolower($match[1]), $actual)) {
            throw new \RuntimeException('MaxMind database archive checksum verification failed; the existing database was kept.');
        }
    }

    /** Extract the .mmdb next to the live database, validate it, then swap it in with an atomic rename. */
    private function installDatabase(string $archive, string $databasePath, ?string $expectedEdition): void
    {
        $databasePath = $this->resolveSymlink($databasePath);
        $directory = dirname($databasePath);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }

        // Exclusive create inside the target directory: tempnam() would silently fall back to the system temp
        // dir, turning the final rename into a non-atomic cross-filesystem copy over the live database.
        $temporary = $directory.'/.geoip-'.bin2hex(random_bytes(8)).'.tmp';
        $handle = @fopen($temporary, 'xb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Failed to create a temporary file in "%s"', $directory));
        }

        fclose($handle);

        try {
            $this->extractDatabase($archive, $temporary);
            $this->assertValidDatabase($temporary, $expectedEdition);

            // The temp file is 0600 and owned by the current user; keep the live database's mode and (when
            // permitted, e.g. root updating a www-data database) owner/group so web workers can still read it.
            $existing = @stat($databasePath);
            chmod($temporary, $existing === false ? 0666 & ~umask() : $existing['mode'] & 0777);
            if ($existing !== false) {
                @chown($temporary, $existing['uid']);
                @chgrp($temporary, $existing['gid']);
            }

            if (! rename($temporary, $databasePath)) {
                throw new \RuntimeException(sprintf('Failed to write database file: %s', $databasePath));
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /** Follows symlinks so that the link stays in place and its target is the file that gets replaced. */
    private function resolveSymlink(string $path): string
    {
        if (! is_link($path)) {
            return $path;
        }

        $originalPath = $path;

        // Bounded like the kernel's own limit: a link loop would otherwise never terminate.
        for ($depth = 0; $depth < 40 && is_link($path); $depth++) {
            $target = readlink($path);
            if ($target === false) {
                throw new \RuntimeException(sprintf('Cannot read the symlink "%s".', $path));
            }

            $path = str_starts_with($target, '/') ? $target : dirname($path).'/'.$target;
        }

        $directory = realpath(dirname($path));
        $resolved = $directory === false ? $path : $directory.'/'.basename($path);

        // Renaming over a link that is still a link would replace that link instead of the database.
        if (is_link($resolved)) {
            throw new \RuntimeException(sprintf('The symlink "%s" is cyclic or too deeply nested; the existing database was kept.', $originalPath));
        }

        return $resolved;
    }

    /** Streams the first .mmdb entry out of the archive; entry names are never used as filesystem paths. */
    private function extractDatabase(string $archive, string $destination): void
    {
        /** @var \PharFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \PharData($archive)) as $file) {
            if ($file->isFile() && pathinfo($file->getFilename(), \PATHINFO_EXTENSION) === 'mmdb') {
                $source = fopen($file->getPathname(), 'rb');
                $target = fopen($destination, 'wb');
                if ($source === false || $target === false) {
                    throw new \RuntimeException('Failed to extract the database file from the archive.');
                }

                try {
                    stream_copy_to_stream($source, $target);
                } finally {
                    fclose($source);
                    fclose($target);
                }

                return;
            }
        }

        throw new \Exception('Database file could not be found within archive.');
    }

    private function assertValidDatabase(string $path, ?string $expectedEdition): void
    {
        $reader = new \MaxMind\Db\Reader($path);

        try {
            $type = $reader->metadata()->databaseType;
        } finally {
            $reader->close();
        }

        if ($expectedEdition !== null && $type !== $expectedEdition) {
            throw new \RuntimeException(sprintf('Downloaded database type "%s" does not match the expected "%s".', $type, $expectedEdition));
        }
    }

    /** Opens the database on first use; subclasses overriding `locate()` must use this instead of `$this->reader`. */
    protected function reader(): Reader
    {
        if (! $this->reader instanceof Reader) {
            $path = $this->config('database_path');
            assert(is_string($path), 'Invalid "database_path" config value');

            if (! is_file($path)) {
                throw new \RuntimeException(sprintf('MaxMind database not found at %s. Run "php artisan geoip:update".', $path));
            }

            /** @var list<string> $locales */
            $locales = $this->config('locales', ['en']);
            $this->reader = new Reader($path, $locales);
        }

        return $this->reader;
    }

    /**
     * Provide a temporary directory to perform operations in
     * and ensure it is removed afterward.
     *
     * @param callable(string):void $callback
     *
     * @return void
     */
    protected function withTemporaryDirectory(callable $callback)
    {
        $directory = tempnam(sys_get_temp_dir(), 'maxmind');
        if ($directory === false) {
            throw new \RuntimeException('Failed to create a temporary file');
        }

        if (file_exists($directory)) {
            unlink($directory);
        }

        if (!mkdir($directory) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }

        try {
            $callback($directory);
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    /**
     * Recursively delete the given directory.
     *
     * @param string $directory
     *
     * @return mixed
     */
    protected function deleteDirectory(string $directory)
    {
        if (! file_exists($directory)) {
            return true;
        }

        if (! is_dir($directory)) {
            return unlink($directory);
        }

        $items = @scandir($directory);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (! $this->deleteDirectory($directory.DIRECTORY_SEPARATOR.$item)) {
                return false;
            }
        }

        return rmdir($directory);
    }

    /**
     * HTTPS-only download. Basic-auth credentials are never put in the URL and are not forwarded across hosts.
     * Error messages carry host and path only, with every known secret masked.
     *
     * @param string|null $userPwd "account:key" for HTTP Basic auth
     * @param int|null $maxBytes abort the transfer once more than this many bytes arrive
     */
    protected function downloadFileByUrl(
        string $filename,
        #[\SensitiveParameter]
        string $url,
        #[\SensitiveParameter]
        ?string $userPwd = null,
        ?int $maxBytes = null,
    ): void {
        $fp = @fopen($filename, 'wb+');
        if ($fp === false) {
            throw new \RuntimeException(sprintf('Cannot open %s file for writing.', $filename));
        }

        $options = [
            \CURLOPT_URL => $url,
            \CURLOPT_FAILONERROR => true,
            \CURLOPT_FOLLOWLOCATION => true,
            \CURLOPT_MAXREDIRS => 3,
            \CURLOPT_CONNECTTIMEOUT => 10,
            \CURLOPT_TIMEOUT => 300,
            \CURLOPT_UNRESTRICTED_AUTH => false,
        ];

        if ($maxBytes === null) {
            $options[\CURLOPT_FILE] = $fp;
        } else {
            $options[\CURLOPT_MAXFILESIZE] = $maxBytes;
            $received = 0;
            // Returning fewer bytes than given makes cURL abort; Content-Length alone can be absent or wrong.
            $options[\CURLOPT_WRITEFUNCTION] = static function (mixed $handle, string $data) use ($fp, $maxBytes, &$received): int {
                $received += strlen($data);

                return $received > $maxBytes ? 0 : (int) fwrite($fp, $data);
            };
        }

        if (defined('CURLOPT_PROTOCOLS_STR') && defined('CURLOPT_REDIR_PROTOCOLS_STR')) {
            $options[\CURLOPT_PROTOCOLS_STR] = 'https';
            $options[\CURLOPT_REDIR_PROTOCOLS_STR] = 'https';
        } else {
            $options[\CURLOPT_PROTOCOLS] = \CURLPROTO_HTTPS;
            $options[\CURLOPT_REDIR_PROTOCOLS] = \CURLPROTO_HTTPS;
        }

        if ($userPwd !== null) {
            $options[\CURLOPT_HTTPAUTH] = \CURLAUTH_BASIC;
            $options[\CURLOPT_USERPWD] = $userPwd;
        }

        try {
            $failure = $this->curlTransfer($options);
        } finally {
            fclose($fp);
        }

        if ($failure !== null) {
            throw new \RuntimeException($this->redact(
                sprintf(
                    'Failed to download %s%s: %s (HTTP %d)',
                    (string) parse_url($url, \PHP_URL_HOST),
                    (string) parse_url($url, \PHP_URL_PATH),
                    $failure['error'],
                    $failure['status'],
                ),
                $url,
                $userPwd,
            ));
        }
    }

    /**
     * Runs the transfer; the seam between request construction and error sanitization.
     *
     * @param array<int, mixed> $options
     * @return array{error: string, status: int}|null null on success
     */
    protected function curlTransfer(#[\SensitiveParameter] array $options): ?array
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL');
        }

        curl_setopt_array($ch, $options);

        if (curl_exec($ch) !== false) {
            return null;
        }

        return ['error' => curl_error($ch), 'status' => (int) curl_getinfo($ch, \CURLINFO_RESPONSE_CODE)];
    }

    /** Masks the configured key, the Basic-auth pair and any `license_key` embedded in the request URL. */
    private function redact(string $message, #[\SensitiveParameter] string $url, #[\SensitiveParameter] ?string $userPwd): string
    {
        /** @var mixed $licenseKey */
        $licenseKey = $this->config('license_key');

        $secrets = [is_string($licenseKey) ? $licenseKey : '', (string) $userPwd, ...array_slice(explode(':', (string) $userPwd, 2), 1)];
        foreach (explode('&', (string) parse_url($url, \PHP_URL_QUERY)) as $pair) {
            [$name, $raw] = [...explode('=', $pair, 2), ''];
            if (urldecode($name) === 'license_key') {
                // The raw spelling may differ from any re-encoding of the decoded value (e.g. %53 vs S).
                $secrets[] = $raw;
                $secrets[] = urldecode($raw);
            }
        }

        // Longest first so a decoded value never splits an encoded one.
        usort($secrets, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($secrets as $secret) {
            if ($secret !== '') {
                $message = str_replace([$secret, rawurlencode($secret), urlencode($secret)], '***', $message);
            }
        }

        return $message;
    }

    /**
     * Get localized country name, state name and city name based on config languages
     * @return array<string, array{country: string|null, state_name: string|null, city: string|null}>
     */
    private function getLocalizations(City $record): array
    {
        $localizations = [];

        /** @var list<string> $locales */
        $locales = $this->config('locales', ['en']);
        foreach ($locales as $lang) {
            $localizations[$lang] = [
                'country' => $record->country->names[$lang] ?? null,
                'state_name' => $record->mostSpecificSubdivision->names[$lang] ?? null,
                'city' => $record->city->names[$lang] ?? null,
            ];
        }

        return $localizations;
    }
}
