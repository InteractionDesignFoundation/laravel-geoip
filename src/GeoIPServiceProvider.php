<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class GeoIPServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerGeoIpService();

        if ($this->app->runningInConsole()) {
            $this->registerResources();
            $this->registerGeoIpCommands();
        }

        $this->mergeConfigFrom(__DIR__ . '/../config/geoip.php', 'geoip');
    }

    /**
     * Register currency provider.
     *
     * @return void
     */
    public function registerGeoIpService(): void
    {
        $this->app->singleton('geoip', static fn(Application $app): GeoIP => new GeoIP(
            $app['config']->get('geoip', []),
            $app['cache']
        ));
    }

    /**
     * Register resources.
     *
     * @return void
     */
    public function registerResources(): void
    {
        $this->publishes([
            __DIR__ . '/../config/geoip.php' => config_path('geoip.php'),
        ], 'config');
    }

    /**
     * Register commands.
     *
     * @return void
     */
    public function registerGeoIpCommands(): void
    {
        $this->commands([
            Console\Update::class,
            Console\Clear::class,
        ]);
    }
}
