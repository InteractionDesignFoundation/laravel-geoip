<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Console;

use Illuminate\Console\Command;

class Clear extends Command
{
    /**
     * The console command name.
     *
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
            $this->output->error('Default cache system does not support tags');
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
    protected function isSupported()
    {
        return empty(app('geoip')->config('cache_tags')) === false
            && in_array(config('cache.default'), ['file', 'database'], true) === false;
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
