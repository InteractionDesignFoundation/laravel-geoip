<?php

namespace InteractionDesignFoundation\GeoIP\Console;

use Illuminate\Console\Command;

final class Clear extends Command
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

    /** Execute the console command for Laravel 5.5 and newer. */
    public function handle(): int
    {
        $this->fire();

        return Command::SUCCESS;
    }

    /** Execute the console command. */
    public function fire(): void
    {
        if (! $this->isSupported()) {
            $this->output->error('Default cache system does not support tags');
        }

        $this->performFlush();
    }

    /** Is cache flushing supported. */
    private function isSupported(): bool
    {
        return empty(app('geoip')->config('cache_tags')) === false
            && in_array(config('cache.default'), ['file', 'database']) === false;
    }

    /** Flush the cache. */
    private function performFlush(): void
    {
        $this->output->write("Clearing cache...");

        app('geoip')->getCache()->flush();

        $this->output->writeln("<info>complete</info>");
    }
}
