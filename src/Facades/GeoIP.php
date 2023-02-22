<?php

namespace InteractionDesignFoundation\GeoIP\Facades;

use Illuminate\Support\Facades\Facade;

class GeoIP extends Facade
{
    /** Get the registered name of the component. */
    protected static function getFacadeAccessor(): string
    {
        return 'geoip';
    }
}