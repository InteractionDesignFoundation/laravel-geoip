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

    /**
     * Execute the console command for Laravel 5.5 and newer.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->fire();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire(): void
    {
        // Get default service
        $service = app('geoip')->getService();

        // Ensure the selected service supports updating
        if (method_exists($service, 'update') === false) {
            $this->info('The current service "' . get_class($service) . '" does not support updating.');

            return;
        }

        $this->comment('Updating...');

        // Perform update
        if ($result = $service->update()) {
            $this->info($result);
            return;
        }

        $this->error('Update failed!');
    }
}
