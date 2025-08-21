<?php

namespace Core\Routing\Attributes;

use Attribute;

/**
 * Defines caching rules for a controller method's response.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Cache
{
    /**
     * @param int $ttl Time to live in seconds.
     * @param array<string> $tags Cache tags for invalidation (requires a PSR-6 cache adapter that supports tags).
     * @param string|null $key A custom cache key. If null, one will be generated from the request URI.
     */
    public function __construct(
        public int $ttl,
        public array $tags = [],
        public ?string $key = null,
    ) {
    }
}
