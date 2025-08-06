<?php

namespace Modules\Cms\Application\Queries;

use Core\Contracts\Cache\Repository as CacheRepository;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * A decorator for PageFinder that adds a caching layer.
 *
 * It intercepts calls to find pages and first checks a cache store. If the page
 * data is present in the cache, it's returned immediately. If not, it delegates
 * the call to the underlying (decorated) PageFinder, stores the result in the
 * cache for subsequent requests, and then returns it.
 *
 * This pattern is useful for improving performance by reducing database load
 * for frequently accessed, rarely changing data.
 */
class CachingPageFinder implements PageFinderInterface
{
    /**
     * The cache duration in seconds (e.g., 3600 for 1 hour).
     */
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly PageFinder $decoratedFinder,
        private readonly CacheRepository $cache,
    ) {
    }

    public function findById(int $id): Page
    {
        $cacheKey = "page:{$id}";

        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($id) {
            return $this->decoratedFinder->findById($id);
        });
    }
}
