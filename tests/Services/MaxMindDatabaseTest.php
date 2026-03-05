<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use InteractionDesignFoundation\GeoIP\Services\MaxMindDatabase;
use InteractionDesignFoundation\GeoIP\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(MaxMindDatabase::class)]
class MaxMindDatabaseTest extends TestCase
{
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
    public function should_throw_runtime_exception_when_download_url_is_unreachable(): void
    {
        [$service] = $this->getService();
        $testableService = new TestableMaxMindDatabase($service);

        $targetFile = tempnam(sys_get_temp_dir(), 'geoip_test_');
        assert(is_string($targetFile));

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Failed to open URL for reading:');

            $testableService->exposedDownloadFileByUrl($targetFile, 'file:///nonexistent/path/that/does/not/exist.tar.gz');
        } finally {
            if (file_exists($targetFile)) {
                unlink($targetFile);
            }
        }
    }

    #[Test]
    public function should_throw_runtime_exception_when_curl_target_file_is_not_writable(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('curl extension is not loaded');
        }

        [$service] = $this->getService();
        $testableService = new TestableMaxMindDatabase($service);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot open');

        $testableService->exposedDownloadFileByUrlViaCurl(
            '/nonexistent/directory/file.tar.gz',
            'https://example.com/nonexistent.tar.gz'
        );
    }

    #[Test]
    public function should_throw_runtime_exception_when_update_url_is_invalid(): void
    {
        $config = $this->getConfig()['services']['maxmind_database'];
        $config['update_url'] = 'file:///nonexistent/path/invalid.tar.gz';

        $service = new MaxMindDatabase($config);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to open URL for reading:');

        $service->update();
    }

    /** @return list{\InteractionDesignFoundation\GeoIP\Contracts\ServiceInterface, array<string, mixed>} */
    protected function getService(): array
    {
        $config = $this->getConfig()['services']['maxmind_database'];

        $service = new $config['class']($config);

        return [$service, $config];
    }
}

/**
 * Test subclass that exposes protected methods of MaxMindDatabase for testing.
 */
class TestableMaxMindDatabase
{
    private MaxMindDatabase $service;

    public function __construct(MaxMindDatabase $service)
    {
        $this->service = $service;
    }

    public function exposedDownloadFileByUrl(string $filename, string $url): void
    {
        $reflection = new \ReflectionMethod($this->service, 'downloadFileByUrl');
        $reflection->invoke($this->service, $filename, $url);
    }

    /**
     * Exercises the curl branch of downloadFileByUrl.
     *
     * Because allow_url_fopen is a PHP_INI_SYSTEM directive and cannot be
     * changed at runtime with ini_set(), we cannot force downloadFileByUrl
     * into its curl branch during tests. This helper reproduces the curl
     * code path from MaxMindDatabase::downloadFileByUrl() so we can verify
     * that an unwritable target file is rejected with a RuntimeException.
     */
    public function exposedDownloadFileByUrlViaCurl(string $filename, string $url): void
    {
        $fp = @fopen($filename, 'wb+');
        if ($fp === false) {
            throw new \RuntimeException(sprintf('Cannot open %s file for writing.', $filename));
        }

        $ch = curl_init();
        curl_setopt($ch, \CURLOPT_URL, $url);
        curl_setopt($ch, \CURLOPT_FILE, $fp);
        curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, true);
        try {
            $result = curl_exec($ch);
            if ($result === false) {
                $error = curl_error($ch);
                throw new \RuntimeException(sprintf('Failed to download file via curl: %s', $error));
            }
        } finally {
            curl_close($ch);
            fclose($fp);
        }
    }
}
