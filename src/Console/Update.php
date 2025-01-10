<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Console;

use Illuminate\Console\Command;
use InteractionDesignFoundation\GeoIP\Exceptions\MissingConfigurationException;

class Update extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'geoip:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update GeoIP database files to the latest version';

    public function handle(): int
    {
        // Get default service
        try {
            /** @var \InteractionDesignFoundation\GeoIP\Contracts\ServiceInterface $service */
            $service = app('geoip')->getService();
        } catch (MissingConfigurationException $missingConfigurationException) {
            $this->components->error($missingConfigurationException->getMessage());

            return static::FAILURE;
        }

        // Ensure the selected service supports updating
        if (method_exists($service, 'update') === false) {
            $this->info('The current service "' . $service::class . '" does not support updating.');

            return static::SUCCESS;
        }

        $this->comment('Updating...');

        // Perform update
        if ($result = $service->update()) {
            $this->info($result);
        } else {
            $this->error('Update failed!');
            return static::FAILURE;
        }

        return static::SUCCESS;
    }
}
