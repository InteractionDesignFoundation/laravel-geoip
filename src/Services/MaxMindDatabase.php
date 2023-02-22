<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use Illuminate\Support\Facades\Storage;
use InteractionDesignFoundation\GeoIP\Location;
use PharData;
use Exception;
use GeoIp2\Database\Reader;

class MaxMindDatabase extends AbstractService
{
    /** Service reader instance. */
    protected Reader $reader;

    /** The "booting" method of the service.
     *
     * @throws \MaxMind\Db\Reader\InvalidDatabaseException
     */
    public function boot(): void
    {
        $path = $this->config('database_path');
        assert(is_string($path));

        // Copy test database for now
        if (! is_file($path)) {
            $concurrentDirectory = dirname($path);
            if (! mkdir($concurrentDirectory) && ! is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }

            copy(__DIR__ . '/../../resources/geoip.mmdb', $path);
        }

        $locales = $this->config('locales', ['en']);
        assert(is_array($locales));

        $this->reader = new Reader($path, $locales);
    }

    public function locate(string $ip): Location
    {
        $record = $this->reader->city($ip);

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
        ]);
    }

    /**
     * Update function for service.
     *
     * @return string
     * @throws Exception
     */
    public function update(): string
    {
        if ($this->config('database_path', false) === false) {
            throw new Exception('Database path not set in config file.');
        }

        $this->withTemporaryDirectory(function ($directory) {
            $tarFile = sprintf('%s/maxmind.tar.gz', $directory);

            $path = $this->config('update_url');
            assert(is_string($path));

            file_put_contents($tarFile, fopen($path, 'rb'));

            $archive = new PharData($tarFile);

            $file = $this->findDatabaseFile($archive);

            $relativePath = "{$archive->getFilename()}/{$file->getFilename()}";

            $archive->extractTo($directory, $relativePath);

            $databasePath = $this->config('database_path');
            assert(is_string($databasePath));

            Storage::put($databasePath, fopen("$directory/$relativePath", 'rb'));
        });

        $databasePath = $this->config('database_path');
        assert(is_string($databasePath));

        return "Database file ($databasePath) updated.";
    }

    /** Provide a temporary directory to perform operations in and ensure it is removed afterwards. */
    protected function withTemporaryDirectory(callable $callback): void
    {
        $directory = tempnam(sys_get_temp_dir(), 'maxmind');
        assert(is_string($directory));

        if (file_exists($directory)) {
            unlink($directory);
        }

        if (! mkdir($directory) && ! is_dir($directory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }

        try {
            $callback($directory);
        } finally {
            $this->deleteDirectory($directory);
        }
    }

    /**
     * Recursively search the given archive to find the .mmdb file.
     *
     * @param \PharData $archive
     *
     * @return mixed
     * @throws \Exception
     */
    protected function findDatabaseFile($archive)
    {
        foreach ($archive as $file) {
            if ($file->isDir()) {
                return $this->findDatabaseFile(new PharData($file->getPathName()));
            }

            if (pathinfo($file, PATHINFO_EXTENSION) === 'mmdb') {
                return $file;
            }
        }

        throw new Exception('Database file could not be found within archive.');
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

        $items = scandir($directory);
        assert(is_array($items));

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (! $this->deleteDirectory($directory . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($directory);
    }
}
