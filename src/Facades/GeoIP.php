<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \InteractionDesignFoundation\GeoIP\Location getLocation(string|null $ip)
 * @method static string getClientIP()
 * @method static string getCurrency(string $iso)
 * @method static \InteractionDesignFoundation\GeoIP\Contracts\ServiceInterface getService()
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
