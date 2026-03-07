<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \InteractionDesignFoundation\GeoIP\Location getLocation(string $ip = null)
 * @method static string|null getCurrency(string $iso)
 * @method static \InteractionDesignFoundation\GeoIP\Contracts\ServiceInterface getService()
 * @method static \InteractionDesignFoundation\GeoIP\Cache getCache()
 * @method static string getClientIP()
 * @method static mixed config(string $key, array|bool|int|null|string $default = null)
 *
 * @see \InteractionDesignFoundation\GeoIP\GeoIP
 */
final class GeoIP extends Facade
{
    /**
     * Get the registered name of the component.
     * @return string
     */
    #[\Override]
    protected static function getFacadeAccessor(): string
    {
        return 'geoip';
    }
}
