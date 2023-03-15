<?php

namespace InteractionDesignFoundation\GeoIP\Console;

use Illuminate\Console\Command;

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

    /** Execute the console command for Laravel 5.5 and newer. */
    public function handle(): int
    {
        $this->fire();

        return Command::SUCCESS;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire(): void
    {
        // Get default service
        /** @var \InteractionDesignFoundation\GeoIP\GeoIP $geoip */
        $geoip = app('geoip');

        /** @var \InteractionDesignFoundation\GeoIP\GeoIP $service */
        $service = $geoip->getService();

        // Ensure the selected service supports updating
        if (! method_exists($service, 'update')) {
            $this->info('The current service "' . get_class($service) . '" does not support updating.');

            return;
        }

        $this->comment('Updating...');

        // Perform update
        $result = $service->update();

        if ($result) {
            $this->info($result);
            return;
        }

        $this->error('Update failed!');
    }
}
