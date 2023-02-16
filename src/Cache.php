<?php

namespace InteractionDesignFoundation\GeoIP;

use Illuminate\Cache\CacheManager;

class Cache
{
    /** Instance of cache manager. */
    protected CacheManager|\Illuminate\Cache\TaggedCache $cache;

    /** Lifetime of the cache. */
    protected int $expires;

    /** Create a new cache instance. */
    public function __construct(CacheManager $cache, array $tags, int $expires = 30)
    {
        $this->cache = $tags ? $cache->tags($tags) : $cache;
        $this->expires = $expires;
    }

    /** Get an item from the cache. */
    public function get(string $name): ?Location
    {
        $value = $this->cache->get($name);

        return is_array($value)
            ? new Location($value)
            : null;
    }

    /** Store an item in cache. */
    public function set(string $name, Location $location): bool
    {
        return $this->cache->put($name, $location->toArray(), $this->expires);
    }

    /** Flush cache for tags. */
    public function flush(): bool
    {
        return $this->cache->flush();
    }
}