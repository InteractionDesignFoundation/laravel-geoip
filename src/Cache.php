<?php

namespace InteractionDesignFoundation\GeoIP;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Repository;

final class Cache
{
    protected CacheManager | TaggedCache $cache;

    /**
     * Create a new cache instance.
     * @param list<string> $tags
     */
    public function __construct(
        CacheManager | TaggedCache $cache,
        array $tags = [],
        private readonly int $expires = 30
    ) {
        $this->cache = $cache;
        if ($this->cache instanceof TaggedCache) {
            $this->cache->tags($tags);
        }
    }

    /** Get an item from the cache. */
    public function get(string $name): ?LocationResponse
    {
        $value = $this->cache->get($name);

        return $value instanceof LocationResponse ? $value : null;
    }

    /** Store an item in cache. */
    public function set(string $name, LocationResponse $location): void
    {
        $this->cache->put($name, $location->toArray(), $this->expires);
    }

    public function flush(): bool
    {
        if (method_exists($this->cache, 'flush')) {
            $this->cache->flush();
        }

        return true;
    }
}