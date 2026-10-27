<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use Illuminate\Filesystem\Filesystem;
use InteractionDesignFoundation\GeoIP\Services\MaxMindDatabase;
use InteractionDesignFoundation\GeoIP\Tests\TestCase;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(MaxMindDatabase::class)]
final class MaxMindDatabaseTest extends TestCase
{
    private string $workDir;

    private int $archiveCounter = 0;

    #[Before]
    public function createWorkDir(): void
    {
        $this->workDir = sys_get_temp_dir().'/geoip_'.uniqid('', true);
        mkdir($this->workDir);
    }

    #[After]
    public function deleteWorkDir(): void
    {
        (new Filesystem())->deleteDirectory($this->workDir);
    }

    #[Test]
    public function should_return_config_value(): void
    {
        [$service, $config] = $this->getService();

        $this->assertSame($service->config('database_path'), $config['database_path']);
    }

    #[Test]
    public function should_return_valid_location(): void
    {
        [$service] = $this->getService();

        $location = $service->locate('81.2.69.142');

        $this->assertInstanceOf(\InteractionDesignFoundation\GeoIP\Location::class, $location);
        $this->assertSame('81.2.69.142', $location->ip);
        $this->assertFalse($location->default);
    }

    #[Test]
    public function should_return_invalid_location_for_special_addresses(): void
    {
        [$service] = $this->getService();

        try {
            $location = $service->locate('1.1.1.1');
            $this->assertFalse($location->default);
        } catch (\GeoIp2\Exception\AddressNotFoundException $addressNotFoundException) {
            $this->assertSame('The address 1.1.1.1 is not in the database.', $addressNotFoundException->getMessage());
        }
    }

    #[Test]
    public function failed_download_message_does_not_leak_license_key_from_legacy_url(): void
    {
        $config = $this->isolatedConfig();
        unset($config['license_key'], $config['account_id']);
        $config['update_url'] = 'https://download.example/app/geoip_download?edition_id=GeoIP2-City&license_key=SECRETKEY&suffix=tar.gz';
        $service = new FailingTransportMaxMindDatabase($config, 'Could not resolve host: SECRETKEY.invalid (license_key=SECRETKEY)');

        $exception = $this->thrownBy($service->update(...));

        $this->assertStringNotContainsString('SECRETKEY', $exception->getMessage());
        $this->assertStringContainsString('download.example/app/geoip_download', $exception->getMessage());
        $this->assertStringNotContainsString('suffix=', $exception->getMessage());
    }

    #[Test]
    public function failed_download_message_does_not_leak_percent_encoded_legacy_key(): void
    {
        $config = $this->isolatedConfig();
        unset($config['license_key'], $config['account_id']);
        $config['update_url'] = 'https://download.example/app/geoip_download?edition_id=GeoIP2-City&license_key=%53ECRET%2BKEY&suffix=tar.gz';
        $service = new FailingTransportMaxMindDatabase($config, 'redirected to %53ECRET%2BKEY.invalid / SECRET+KEY');

        $exception = $this->thrownBy($service->update(...));

        $this->assertStringNotContainsString('ECRET', $exception->getMessage());
    }

    #[Test]
    public function basic_auth_download_sends_credentials_as_options_and_never_leaks_them(): void
    {
        $service = new FailingTransportMaxMindDatabase($this->isolatedConfig(), 'bad credentials 12345:SECRETKEY');

        $exception = $this->thrownBy($service->update(...));

        $this->assertStringNotContainsString('SECRETKEY', $exception->getMessage());
        $this->assertStringContainsString('(HTTP 401)', $exception->getMessage());

        $options = $service->transfers[0];
        $this->assertSame('https://download.maxmind.com/geoip/databases/GeoIP2-City/download?suffix=tar.gz.sha256', $options[\CURLOPT_URL]);
        $this->assertSame('12345:SECRETKEY', $options[\CURLOPT_USERPWD]);
        $this->assertSame(\CURLAUTH_BASIC, $options[\CURLOPT_HTTPAUTH]);
        $this->assertFalse($options[\CURLOPT_UNRESTRICTED_AUTH]);
        $this->assertTrue($options[\CURLOPT_FAILONERROR]);
        $this->assertTrue($options[\CURLOPT_FOLLOWLOCATION]);
        $protocols = defined('CURLOPT_PROTOCOLS_STR')
            ? [\CURLOPT_PROTOCOLS_STR => 'https', \CURLOPT_REDIR_PROTOCOLS_STR => 'https']
            : [\CURLOPT_PROTOCOLS => \CURLPROTO_HTTPS, \CURLOPT_REDIR_PROTOCOLS => \CURLPROTO_HTTPS];
        foreach ($protocols as $option => $expected) {
            $this->assertSame($expected, $options[$option]);
        }
    }

    #[Test]
    public function exception_trace_does_not_contain_credentials(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');

        try {
            $service = new FailingTransportMaxMindDatabase($this->isolatedConfig(), 'denied');
            $basicAuth = $this->thrownBy($service->update(...));

            $config = $this->isolatedConfig();
            $config['update_url'] = 'https://download.example/app/geoip_download?edition_id=GeoIP2-City&license_key=SECRETKEY&suffix=tar.gz';
            $legacy = $this->thrownBy((new FailingTransportMaxMindDatabase($config, 'denied'))->update(...));
        } finally {
            ini_set('zend.exception_ignore_args', (string) $previous);
        }

        foreach ([$basicAuth, $legacy] as $exception) {
            $frames = array_filter($exception->getTrace(), static fn(array $frame): bool => ($frame['function'] ?? '') === 'downloadFileByUrl');
            $this->assertNotEmpty($frames);
            $this->assertStringNotContainsString('SECRETKEY', print_r($frames, true));
        }
    }

    #[Test]
    public function validation_failures_are_redacted_too(): void
    {
        $config = $this->isolatedConfig();
        unset($config['license_key'], $config['account_id']);
        $config['update_url'] = 'https://download.example/app/geoip_download?edition_id=SECRETKEY&license_key=SECRETKEY&suffix=tar.gz';
        $archive = $this->makeArchive($this->fixture());
        $service = new FakeDownloadMaxMindDatabase($config, $archive, $this->checksumFor($archive));

        $exception = $this->thrownBy($service->update(...));

        $this->assertStringContainsString('does not match', $exception->getMessage());
        $this->assertStringNotContainsString('SECRETKEY', $exception->getMessage());
    }

    #[Test]
    public function custom_update_url_without_edition_falls_back_to_configured_edition(): void
    {
        $config = $this->isolatedConfig();
        $config['edition_id'] = 'GeoLite2-Country';
        $config['update_url'] = 'https://download.example/db?suffix=tar.gz';
        $original = $this->installOriginalDatabase($config['database_path']);
        $archive = $this->makeArchive($this->fixture());
        $service = new FakeDownloadMaxMindDatabase($config, $archive, $this->checksumFor($archive));

        $exception = $this->thrownBy($service->update(...));

        $this->assertStringContainsString('"GeoLite2-Country"', $exception->getMessage());
        $this->assertSame($original, file_get_contents($config['database_path']));
    }

    #[Test]
    public function integer_account_id_uses_basic_auth(): void
    {
        $config = $this->isolatedConfig();
        $config['account_id'] = 12345;
        $service = new FailingTransportMaxMindDatabase($config, 'denied');

        $this->thrownBy($service->update(...));

        $this->assertSame('12345:SECRETKEY', $service->transfers[0][\CURLOPT_USERPWD]);
        $this->assertStringNotContainsString('license_key', (string) $service->transfers[0][\CURLOPT_URL]);
    }

    #[Test]
    public function checksum_download_is_capped_and_garbage_checksum_is_rejected_cleanly(): void
    {
        $service = new FailingTransportMaxMindDatabase($this->isolatedConfig(), 'denied');
        $this->thrownBy($service->update(...));

        $writer = $service->transfers[0][\CURLOPT_WRITEFUNCTION];
        $this->assertIsCallable($writer);
        $this->assertSame(1024, $service->transfers[0][\CURLOPT_MAXFILESIZE]);
        $this->assertSame(0, $writer(null, str_repeat('a', 2000)));

        $config = $this->isolatedConfig();
        $original = $this->installOriginalDatabase($config['database_path']);
        $archive = $this->makeArchive($this->fixture());
        $service = new FakeDownloadMaxMindDatabase($config, $archive, str_repeat('z', 5_000_000));

        $exception = $this->thrownBy($service->update(...));

        $this->assertStringContainsString('checksum', $exception->getMessage());
        $this->assertSame(1024, $service->maxChecksumBytes);
        $this->assertSame($original, file_get_contents($config['database_path']));
        $this->assertSame([basename($config['database_path'])], $this->databaseDirFiles($config));
    }

    #[Test]
    public function update_preserves_existing_database_mode(): void
    {
        $config = $this->isolatedConfig();
        $this->installOriginalDatabase($config['database_path']);
        chmod($config['database_path'], 0640);
        $archive = $this->makeArchive($this->fixture());

        (new FakeDownloadMaxMindDatabase($config, $archive, $this->checksumFor($archive)))->update();

        clearstatcache();
        $this->assertSame(0640, (int) fileperms($config['database_path']) & 0777);
    }

    #[Test]
    public function update_fails_when_database_directory_is_not_writable(): void
    {
        $config = $this->isolatedConfig();
        $this->installOriginalDatabase($config['database_path']);
        $archive = $this->makeArchive($this->fixture());
        $service = new FakeDownloadMaxMindDatabase($config, $archive, $this->checksumFor($archive));
        chmod(dirname($config['database_path']), 0555);

        try {
            if (is_writable(dirname($config['database_path']))) {
                $this->markTestSkipped('Cannot make a directory read-only (running as root?)');
            }

            $exception = $this->thrownBy($service->update(...));
        } finally {
            chmod(dirname($config['database_path']), 0755);
        }

        $this->assertStringContainsString('temporary file', $exception->getMessage());
        $this->assertSame('original-database-bytes', file_get_contents($config['database_path']));
    }

    #[Test]
    public function download_rejects_non_https_urls(): void
    {
        $service = new MaxMindDatabase($this->isolatedConfig());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to download');

        (new TestableMaxMindDatabase($service))->exposedDownloadFileByUrl(
            $this->workDir.'/download.tar.gz',
            'file://'.__DIR__.'/../../resources/geoip.mmdb',
        );
    }

    #[Test]
    public function download_fails_when_target_file_is_not_writable(): void
    {
        $service = new MaxMindDatabase($this->isolatedConfig());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot open');

        (new TestableMaxMindDatabase($service))->exposedDownloadFileByUrl(
            '/nonexistent/directory/file.tar.gz',
            'https://example.com/nonexistent.tar.gz',
        );
    }

    #[Test]
    public function checksum_mismatch_keeps_existing_database(): void
    {
        $config = $this->isolatedConfig();
        $original = $this->installOriginalDatabase($config['database_path']);
        $service = new FakeDownloadMaxMindDatabase($config, $this->makeArchive($this->fixture()), str_repeat('0', 64).'  x.tar.gz');

        $exception = $this->thrownBy($service->update(...));

        $this->assertStringContainsString('checksum', $exception->getMessage());

        $this->assertSame($original, file_get_contents($config['database_path']));
        $this->assertSame([basename($config['database_path'])], $this->databaseDirFiles($config));
    }

    #[Test]
    public function invalid_mmdb_in_archive_keeps_existing_database(): void
    {
        $config = $this->isolatedConfig();
        $original = $this->installOriginalDatabase($config['database_path']);
        $archive = $this->makeArchive('this is not an mmdb file');
        $service = new FakeDownloadMaxMindDatabase($config, $archive, $this->checksumFor($archive));

        $this->thrownBy($service->update(...));

        $this->assertSame($original, file_get_contents($config['database_path']));
        $this->assertSame([basename($config['database_path'])], $this->databaseDirFiles($config));
    }

    #[Test]
    public function mmdb_with_unexpected_edition_keeps_existing_database(): void
    {
        $config = $this->isolatedConfig();
        $config['edition_id'] = 'GeoLite2-Country';
        $original = $this->installOriginalDatabase($config['database_path']);
        $archive = $this->makeArchive($this->fixture());
        $service = new FakeDownloadMaxMindDatabase($config, $archive, $this->checksumFor($archive));

        $exception = $this->thrownBy($service->update(...));

        $this->assertStringContainsString('GeoIP2-City', $exception->getMessage());

        $this->assertSame($original, file_get_contents($config['database_path']));
    }

    #[Test]
    public function successful_update_replaces_database_with_basic_auth_permalink(): void
    {
        $config = $this->isolatedConfig();
        $this->installOriginalDatabase($config['database_path']);
        $archive = $this->makeArchive($this->fixture());
        $service = new FakeDownloadMaxMindDatabase($config, $archive, $this->checksumFor($archive));

        $message = $service->update();

        $this->assertStringContainsString($config['database_path'], $message);
        $this->assertSame($this->fixture(), file_get_contents($config['database_path']));
        $this->assertSame([basename($config['database_path'])], $this->databaseDirFiles($config));
        $this->assertSame(
            [
                ['https://download.maxmind.com/geoip/databases/GeoIP2-City/download?suffix=tar.gz.sha256', '12345:SECRETKEY'],
                ['https://download.maxmind.com/geoip/databases/GeoIP2-City/download?suffix=tar.gz', '12345:SECRETKEY'],
            ],
            $service->requests,
        );
        $this->assertSame('81.2.69.142', $service->locate('81.2.69.142')->ip);
    }

    #[Test]
    public function successful_update_with_legacy_url_downloads_matching_checksum_and_sends_no_credentials(): void
    {
        $config = $this->isolatedConfig();
        $config['update_url'] = 'https://example.test/app/geoip_download?edition_id=GeoIP2-City&license_key=SECRETKEY&suffix=tar.gz';
        $archive = $this->makeArchive($this->fixture());
        $service = new FakeDownloadMaxMindDatabase($config, $archive, $this->checksumFor($archive));

        $service->update();

        $this->assertSame($this->fixture(), file_get_contents($config['database_path']));
        $this->assertSame(
            [
                ['https://example.test/app/geoip_download?edition_id=GeoIP2-City&license_key=SECRETKEY&suffix=tar.gz.sha256', null],
                ['https://example.test/app/geoip_download?edition_id=GeoIP2-City&license_key=SECRETKEY&suffix=tar.gz', null],
            ],
            $service->requests,
        );
    }

    #[Test]
    public function update_through_symlink_keeps_the_link_and_replaces_its_target(): void
    {
        $config = $this->isolatedConfig();
        mkdir($this->workDir.'/real');
        file_put_contents($this->workDir.'/real/geoip.mmdb', 'original-database-bytes');
        mkdir(dirname($config['database_path']));
        symlink('../real/geoip.mmdb', $config['database_path']);
        $archive = $this->makeArchive($this->fixture());

        (new FakeDownloadMaxMindDatabase($config, $archive, $this->checksumFor($archive)))->update();

        $this->assertTrue(is_link($config['database_path']));
        $this->assertSame('../real/geoip.mmdb', readlink($config['database_path']));
        $this->assertSame($this->fixture(), file_get_contents($this->workDir.'/real/geoip.mmdb'));
        $this->assertSame(['geoip.mmdb'], array_values(array_diff((array) scandir($this->workDir.'/real'), ['.', '..'])));
        $this->assertSame(['geoip.mmdb'], $this->databaseDirFiles($config));
    }

    #[Test]
    public function update_through_dangling_symlink_creates_the_target(): void
    {
        $config = $this->isolatedConfig();
        mkdir($this->workDir.'/real');
        mkdir(dirname($config['database_path']));
        symlink($this->workDir.'/real/geoip.mmdb', $config['database_path']);
        $archive = $this->makeArchive($this->fixture());

        (new FakeDownloadMaxMindDatabase($config, $archive, $this->checksumFor($archive)))->update();

        $this->assertTrue(is_link($config['database_path']));
        $this->assertSame($this->fixture(), file_get_contents($this->workDir.'/real/geoip.mmdb'));
    }

    #[Test]
    public function subclass_overriding_locate_can_use_the_reader(): void
    {
        $config = $this->isolatedConfig();
        mkdir(dirname($config['database_path']));
        copy(__DIR__.'/../../resources/geoip.mmdb', $config['database_path']);
        $service = new ReaderUsingMaxMindDatabase($config);

        $this->assertSame('GB', $service->locate('81.2.69.142')->iso_code);
    }

    #[Test]
    public function missing_database_is_not_replaced_by_the_bundled_fixture(): void
    {
        $config = $this->isolatedConfig();

        $service = new MaxMindDatabase($config);

        $this->assertFileDoesNotExist($config['database_path']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('MaxMind database not found at %s. Run "php artisan geoip:update".', $config['database_path']));

        $service->locate('81.2.69.142');
    }

    #[Test]
    public function get_location_returns_default_location_when_database_is_missing(): void
    {
        $config = $this->isolatedConfig();
        $geoIp = $this->makeGeoIP([
            'service' => 'maxmind_database',
            'cache' => 'none',
            'log_failures' => false,
            'services' => ['maxmind_database' => ['class' => MaxMindDatabase::class] + $config],
        ]);

        $this->assertTrue($geoIp->getLocation('81.2.69.142')->default);
        $this->assertFileDoesNotExist($config['database_path']);
    }

    #[Test]
    public function delete_directory_returns_false_when_directory_is_unreadable(): void
    {
        [$service] = $this->getService();

        $directory = sys_get_temp_dir().'/geoip_unreadable_'.uniqid('', true);
        mkdir($directory);
        chmod($directory, 0000);

        try {
            if (is_readable($directory)) {
                $this->markTestSkipped('Cannot make a directory unreadable (running as root?)');
            }

            $this->assertFalse((new TestableMaxMindDatabase($service))->exposedDeleteDirectory($directory));
        } finally {
            chmod($directory, 0755);
            rmdir($directory);
        }
    }

    /** @return array{class: class-string, database_path: string, account_id: int|string, license_key: string, edition_id: string, locales: list<string>, update_url?: string} */
    private function isolatedConfig(): array
    {
        return [
            'class' => MaxMindDatabase::class,
            'database_path' => $this->workDir.'/db/geoip.mmdb',
            'account_id' => '12345',
            'license_key' => 'SECRETKEY',
            'edition_id' => 'GeoIP2-City',
            'locales' => ['en'],
        ];
    }

    private function installOriginalDatabase(string $path): string
    {
        mkdir(dirname($path), 0755, true);
        file_put_contents($path, 'original-database-bytes');

        return 'original-database-bytes';
    }

    /** @return non-empty-string */
    private function fixture(): string
    {
        $bytes = file_get_contents(__DIR__.'/../../resources/geoip.mmdb');
        assert(is_string($bytes) && $bytes !== '');

        return $bytes;
    }

    /** Packs $database into a MaxMind-style tar.gz (a dated directory holding the .mmdb) and returns its path. */
    private function makeArchive(string $database): string
    {
        $tar = sprintf('%s/archive%d.tar', $this->workDir, ++$this->archiveCounter);

        $phar = new \PharData($tar);
        $phar->addFromString('GeoIP2-City_20240101/COPYRIGHT.txt', 'copyright');
        $phar->addFromString('GeoIP2-City_20240101/GeoIP2-City.mmdb', $database);
        $phar->compress(\Phar::GZ);

        return $tar.'.gz';
    }

    /** Fails outside the catch block: PHPUnit's AssertionFailedError is itself a RuntimeException. */
    private function thrownBy(callable $action): \Throwable
    {
        $thrown = null;

        try {
            $action();
        } catch (\Throwable $throwable) {
            $thrown = $throwable;
        }

        $this->assertInstanceOf(\Throwable::class, $thrown, 'Expected an exception to be thrown.');

        return $thrown;
    }

    private function checksumFor(string $archive): string
    {
        return (string) hash_file('sha256', $archive).'  archive.tar.gz';
    }

    /**
     * @param array{database_path: string, ...} $config
     * @return list<string>
     */
    private function databaseDirFiles(array $config): array
    {
        $files = scandir(dirname($config['database_path']));
        assert(is_array($files));

        return array_values(array_diff($files, ['.', '..']));
    }

    /** @return list{MaxMindDatabase, array{database_path: string, ...}} */
    protected function getService(): array
    {
        $config = $this->getConfig()['services']['maxmind_database'];

        $service = new MaxMindDatabase($config);

        return [$service, $config];
    }
}

/**
 * Test subclass that exposes protected methods of MaxMindDatabase for testing.
 */
final readonly class TestableMaxMindDatabase
{
    public function __construct(private MaxMindDatabase $service) {}

    public function exposedDownloadFileByUrl(string $filename, string $url, ?string $userPwd = null): void
    {
        $reflection = new \ReflectionMethod($this->service, 'downloadFileByUrl');
        $reflection->invoke($this->service, $filename, $url, $userPwd);
    }

    public function exposedDeleteDirectory(string $directory): mixed
    {
        $reflection = new \ReflectionMethod($this->service, 'deleteDirectory');

        return $reflection->invoke($this->service, $directory);
    }
}

/**
 * Serves a prepared archive and checksum instead of hitting the network.
 */
final class FakeDownloadMaxMindDatabase extends MaxMindDatabase
{
    /** @var list<array{string, string|null}> */
    public array $requests = [];

    public ?int $maxChecksumBytes = null;

    /** @param array<string, mixed> $config */
    public function __construct(array $config, private readonly string $archive, private readonly string $checksum)
    {
        parent::__construct($config);
    }

    #[\Override]
    protected function downloadFileByUrl(string $filename, string $url, ?string $userPwd = null, ?int $maxBytes = null): void
    {
        $this->requests[] = [$url, $userPwd];

        if (str_ends_with($url, '.sha256')) {
            $this->maxChecksumBytes = $maxBytes;
            file_put_contents($filename, $this->checksum);

            return;
        }

        copy($this->archive, $filename);
    }
}

/**
 * A 4.x-style subclass: overrides locate() and talks to the reader directly.
 */
final class ReaderUsingMaxMindDatabase extends MaxMindDatabase
{
    #[\Override]
    public function locate($ip): \InteractionDesignFoundation\GeoIP\Location
    {
        return $this->hydrate([
            'ip' => $ip,
            'iso_code' => $this->reader()->city($ip)->country->isoCode,
        ]);
    }
}

/**
 * Fails every transfer with a canned cURL error and records the options it was given.
 */
final class FailingTransportMaxMindDatabase extends MaxMindDatabase
{
    /** @var list<array<int, mixed>> */
    public array $transfers = [];

    /** @param array<string, mixed> $config */
    public function __construct(array $config, private readonly string $error)
    {
        parent::__construct($config);
    }

    #[\Override]
    protected function curlTransfer(array $options): array
    {
        $this->transfers[] = $options;

        return ['error' => $this->error, 'status' => 401];
    }
}
