<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Console;

use Illuminate\Console\Command;

class Clear extends Command
{
    /**
     * The console command name.
     * @var string
     */
    protected $name = 'geoip:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear GeoIP cached locations.';

    public function handle(): int
    {
        if ($this->isSupported() === false) {
            $this->output->error(
                'Cannot selectively clear GeoIP cache: either cache tags are not configured'
                .' or the active cache driver does not support tagging.'
            );
            return self::FAILURE;
        }

        $this->performFlush();

        return self::SUCCESS;
    }

    /**
     * Is cache flushing supported.
     *
     * @return bool
     */
    protected function isSupported(): bool
    {
        return !empty(app('geoip')->config('cache_tags'))
            && app(\Illuminate\Cache\CacheManager::class)->supportsTags();
    }

    /**
     * Flush the cache.
     *
     * @return void
     */
    protected function performFlush()
    {
        $this->output->write("Clearing cache...");

        app('geoip')->getCache()->flush();

        $this->output->writeln("<info>complete</info>");
    }
}
