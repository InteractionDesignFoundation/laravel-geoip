<?php

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
        if ($this->isSupported() === false) {
            $this->output->error('Default cache system does not support tags');
            return;
        }

        $this->performFlush();
    }

    /**
     * Is cache flushing supported.
     *
     * @return bool
     */
    protected function isSupported(): bool
    {
        return empty(app('geoip')->config('cache_tags')) === false
            && in_array(config('cache.default'), ['file', 'database']) === false;
    }

    /**
     * Flush the cache.
     *
     * @return void
     */
    protected function performFlush(): void
    {
        $this->output->write("Clearing cache...");

        app('geoip')->getCache()->flush();

        $this->output->writeln("<info>complete</info>");
    }
}
