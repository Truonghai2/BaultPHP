<?php

namespace Core\Debug;

use App\Services\RealtimeDebugService;
use Core\Cache\CacheManager;
use DebugBar\DebugBar;
use Psr\SimpleCache\CacheInterface;

/**
 * A decorator for the CacheManager that returns traceable cache store instances.
 * This allows the DebugBar to collect data on cache hits, misses, writes, and forgets.
 */
class TraceableCacheManager extends CacheManager
{
    protected CacheCollector $collector;
    protected RealtimeDebugService $realtimeService;

    /**
     * TraceableCacheManager constructor.
     *
     * @param CacheManager $manager The original CacheManager instance.
     * @param DebugBar     $debugbar The DebugBar instance to get the collector from.
     */
    public function __construct(CacheManager $manager, DebugBar $debugbar)
    {
        parent::__construct($manager->app);
        $this->stores = $manager->stores; // Copy resolved stores
        /** @var CacheCollector $collector */
        $collector = $debugbar->getCollector('cache');
        $this->collector = $collector;
        $this->realtimeService = $this->app->make(RealtimeDebugService::class);
    }

    /**
     * Get a cache store instance and wrap it in a TraceableCacheStore.
     *
     * @param string|null $name
     * @return CacheInterface
     */
    public function store(string $name = null): CacheInterface
    {
        $store = parent::store($name);
        $storeName = $name ?? $this->getDefaultDriver();

        return new TraceableCacheStore($store, $this->collector, $storeName, $this->realtimeService);
    }
}
