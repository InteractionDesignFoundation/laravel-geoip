<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use GeoIp2\Database\Reader;
use GeoIp2\Model\City;
use Illuminate\Support\Arr;

class MaxMindDatabase extends AbstractService
{
    /**
     * Service reader instance.
     *
     * @var \GeoIp2\Database\Reader
     */
    protected $reader;

    /**
     * The "booting" method of the service.
     *
     * @return void
     */
    public function boot()
    {
        $this->ensureConfigurationParameterDefined('database_path') ;

        $path = $this->config('database_path');
        assert(is_string($path), 'Invalid "database_path" config value');

        // Copy test database for now
        if (is_file($path) === false) {
            if (!is_dir($concurrentDirectory = dirname($path)) &&
                !mkdir($concurrentDirectory) &&
                !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }

            copy(__DIR__ . '/../../resources/geoip.mmdb', $path);
        }

        $this->reader = new Reader(
            $path,
            $this->config('locales', ['en'])
        );
    }

    /** {@inheritDoc} */
    public function locate($ip)
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
            'localizations' => $this->getLocalizations($record),
        ]);
    }

    /**
     * Update function for service.
     *
     * @return string
     * @throws \Exception
     */
    public function update()
    {
        if ($this->config('database_path', false) === false) {
            throw new \Exception('Database path not set in config file.');
        }

        $this->withTemporaryDirectory(function ($directory): void {
            $tarFile = sprintf('%s/maxmind.tar.gz', $directory);

            $this->downloadFileByUrl($tarFile, $this->config('update_url'));

            $archive = new \PharData($tarFile);

            $file = $this->findDatabaseFile($archive);

            $relativePath = "{$archive->getFilename()}/{$file->getFilename()}";

            $archive->extractTo($directory, $relativePath);

            file_put_contents($this->config('database_path'), fopen("{$directory}/{$relativePath}", 'rb'));
        });

        return "Database file ({$this->config('database_path')}) updated.";
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
     * Recursively search the given archive to find the .mmdb file.
     *
     * @param \PharData $archive
     *
     * @return \PharFileInfo
     * @throws \Exception
     */
    protected function findDatabaseFile(\PharData $archive)
    {
        /** @var \PharFileInfo $file */
        foreach ($archive as $file) {
            if ($file->isDir()) {
                return $this->findDatabaseFile(new \PharData($file->getPathName()));
            }

            if (pathinfo($file->getPathName(), \PATHINFO_EXTENSION) === 'mmdb') {
                return $file;
            }
        }

        throw new \Exception('Database file could not be found within archive.');
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

    protected function downloadFileByUrl(string $filename, string $url): void
    {
        $canUseFopenForUrl = in_array(strtolower((string) ini_get('allow_url_fopen')), ['1', 'on'], true);
        if ($canUseFopenForUrl) {
            file_put_contents($filename, fopen($url, 'rb'));
        } elseif (extension_loaded('curl')) {
            $fp = fopen($filename, 'wb+');
            if ($fp === false) {
                throw new \RuntimeException("Cannot open {$filename} file for writing.");
            }
            $ch = curl_init();
            curl_setopt($ch, \CURLOPT_URL, $url);
            curl_setopt($ch, \CURLOPT_FILE, $fp);
            curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
        } else {
            throw new \RuntimeException('Cannot download the file. Please enable allow_url_fopen or install curl extension.');
        }
    }

    /**
     * Get localized country name, state name and city name based on config languages
     * @return array<string, string|null>
     */
    private function getLocalizations(City $record): array
    {
        $localizations = [];

        foreach ($this->config('locales', ['en']) as $lang) {
            $localizations[$lang]['country'] = Arr::get($record->country->names, $lang);
            $localizations[$lang]['state_name'] = Arr::get($record->mostSpecificSubdivision->names, $lang);
            $localizations[$lang]['city'] = Arr::get($record->city->names, $lang);
        }

        return $localizations;
    }
}
