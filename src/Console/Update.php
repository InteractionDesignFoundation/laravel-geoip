<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Console;

use Illuminate\Console\Command;

/** @psalm-api */
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
        } catch (\Exception $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        // Ensure the selected service supports updating
        if (method_exists($service, 'update') === false) {
            $this->info('The current service "'.$service::class.'" does not support updating.');

            return self::SUCCESS;
        }

        $this->comment('Updating...');

        // Perform update
        try {
            $result = $this->nonEmptyString(\call_user_func([$service, 'update']));
        } catch (\Exception $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($result !== null) {
            $this->info($result);
        } else {
            $this->error('Update failed!');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Services report the update result as a message; anything else is treated as a failure.
     *
     * @return non-empty-string|null
     * @psalm-pure
     */
    private function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
