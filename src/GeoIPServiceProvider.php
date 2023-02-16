<?php

namespace InteractionDesignFoundation\GeoIP;

use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;
use InteractionDesignFoundation\GeoIP\Contracts\Client;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;

class GeoIPServiceProvider extends ServiceProvider
{
    /** Register the service provider. */
    public function register(): void
    {
        $this->registerGeoIpService();

        if ($this->app->runningInConsole()) {
            $this->registerResources();
            $this->registerGeoIpCommands();
        }

        $this->mergeConfigFrom(__DIR__ . '/../config/geoip.php', 'geoip');

        $this->app->bind(Client::class, fn () => new HttpClient());
    }

    /** Register currency provider. */
    public function registerGeoIpService(): void
    {
        $this->app->singleton('geoip', function ($app) {
            return new GeoIP(
                $app->config->get('geoip', []),
                $app['cache']
            );
        });
    }

    /** Register resources. */
    public function registerResources(): void
    {
        $this->publishes([
            __DIR__ . '/../config/geoip.php' => config_path('geoip.php'),
        ], 'config');
    }

    /** Register commands.  */
    public function registerGeoIpCommands(): void
    {
        $this->commands([
            Console\Update::class,
            Console\Clear::class,
        ]);
    }
}
