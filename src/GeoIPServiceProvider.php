<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class GeoIPServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     * @return void
     */
    #[\Override]
    public function register(): void
    {
        $this->registerGeoIpService();

        if ($this->app->runningInConsole()) {
            $this->registerResources();
            $this->registerGeoIpCommands();
        }

        $this->mergeConfigFrom(__DIR__.'/../config/geoip.php', 'geoip');
    }

    /**
     * Register currency provider.
     *
     * @return void
     */
    public function registerGeoIpService(): void
    {
        $this->app->singleton('geoip', static function (Application $app): GeoIP {
            /** @var \Illuminate\Config\Repository $config */
            $config = $app['config'];
            /** @var \Illuminate\Cache\CacheManager $cache */
            $cache = $app['cache'];
            /** @var \Psr\Log\LoggerInterface $log */
            $log = $app['log'];

            return new GeoIP($config->get('geoip', []), $cache, $log);
        });
    }

    /**
     * Register resources.
     *
     * @return void
     */
    public function registerResources(): void
    {
        $this->publishes([
            __DIR__.'/../config/geoip.php' => config_path('geoip.php'),
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
