<?php

namespace InteractionDesignFoundation\GeoIP\Services;

use Illuminate\Support\Facades\Storage;
use InteractionDesignFoundation\GeoIP\Location;
use InteractionDesignFoundation\GeoIP\LocationResponse;
use PharData;
use GeoIp2\Database\Reader;

class MaxMindDatabase extends AbstractService
{
    /** Service reader instance. */
    protected Reader $reader;

    /** The "booting" method of the service. */
    public function boot(): void
    {
        $path = $this->config('database_path');

        // Copy test database for now
        if (is_file($path) === false) {
            if (! mkdir($concurrentDirectory = dirname($path)) && ! is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }

            copy(__DIR__ . '/../../resources/geoip.mmdb', $path);
        }

        $this->reader = new Reader(
            $path, $this->config('locales', ['en'])
        );
    }

    /** @inheritdoc */
    public function locate($ip): Location|LocationResponse
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

    public function hydrate(array $attributes = []): Location|LocationResponse
    {
        if (config('geoip.should_use_dto_response', false)) {
            return LocationResponse::fromMaxMindDatabase($attributes);
        }

        return new Location($attributes);
    }

    /**
     * Update function for service.
     *
     * @throws \Exception
     */
    public function update(): string
    {
        if ($this->config('database_path', false) === false) {
            throw new \Exception('Database path not set in config file.');
        }

        $this->withTemporaryDirectory(function ($directory) {
            $tarFile = sprintf('%s/maxmind.tar.gz', $directory);

            file_put_contents($tarFile, fopen($this->config('update_url'), 'rb'));

            $archive = new PharData($tarFile);

            $file = $this->findDatabaseFile($archive);

            $relativePath = "{$archive->getFilename()}/{$file->getFilename()}";

            $archive->extractTo($directory, $relativePath);

            Storage::put($this->config('database_path'), fopen("$directory/$relativePath", 'rb'));
        });

        return "Database file ({$this->config('database_path')}) updated.";
    }

    /**
     * Provide a temporary directory to perform operations in and ensure
     * it is removed afterwards.
     */
    protected function withTemporaryDirectory(callable $callback): void
    {
        $directory = tempnam(sys_get_temp_dir(), 'maxmind');

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

        throw new \Exception('Database file could not be found within archive.');
    }

    /** Recursively delete the given directory. */
    protected function deleteDirectory(string $directory): bool
    {
        if (! file_exists($directory)) {
            return true;
        }

        if (! is_dir($directory)) {
            return unlink($directory);
        }

        foreach (scandir($directory) as $item) {
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
