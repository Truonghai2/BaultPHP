<?php

namespace Core\Cache;

/**
 * Tagged Cache
 *
 * Provides cache invalidation by tags.
 * Much more efficient than manual key tracking.
 *
 * Usage:
 * ```php
 * cache()->tags(['users', 'user:123'])->put('user:123:profile', $data);
 * cache()->tags(['users'])->flush(); // Invalidate all user-related cache
 * ```
 */
class TaggedCache
{
    private array $tags = [];

    public function __construct(
        private CacheManager $cache,
    ) {
    }

    /**
     * Set tags for this cache operation
     */
    public function tags(array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }

    /**
     * Store an item in the cache with tags
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        // Store the actual value
        $result = $this->cache->put($key, $value, $ttl);

        // Store tag references
        foreach ($this->tags as $tag) {
            $this->addKeyToTag($tag, $key, $ttl);
        }

        return $result;
    }

    /**
     * Get an item from the cache
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($key, $default);
    }

    /**
     * Remember an item in cache with tags
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    /**
     * Flush all cache entries with given tags
     */
    public function flush(): bool
    {
        foreach ($this->tags as $tag) {
            $this->flushTag($tag);
        }

        return true;
    }

    /**
     * Add a key to a tag's set
     */
    private function addKeyToTag(string $tag, string $key, ?int $ttl): void
    {
        $tagKey = $this->getTagKey($tag);
        $keys = $this->cache->get($tagKey, []);

        if (!in_array($key, $keys)) {
            $keys[] = $key;
            $this->cache->put($tagKey, $keys, $ttl ?? 86400); // Default 24h
        }
    }

    /**
     * Flush all keys associated with a tag
     */
    private function flushTag(string $tag): void
    {
        $tagKey = $this->getTagKey($tag);
        $keys = $this->cache->get($tagKey, []);

        foreach ($keys as $key) {
            $this->cache->forget($key);
        }

        $this->cache->forget($tagKey);
    }

    /**
     * Get the cache key for a tag
     */
    private function getTagKey(string $tag): string
    {
        return "tag:{$tag}:keys";
    }

    /**
     * Increment a cached value
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->cache->increment($key, $value);
    }

    /**
     * Decrement a cached value
     */
    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->cache->decrement($key, $value);
    }
}
