<?php

declare(strict_types=1);

namespace Core\GraphQL;

use Core\Support\Facades\Log;
use Closure;
use SplObjectStorage;

/**
 * DataLoader
 *
 * Batching and caching utility for GraphQL resolvers.
 * Prevents N+1 query problems by batching and caching data loads.
 */
class DataLoader
{
    protected array $loaders = [];
    protected array $cache = [];
    protected bool $batchingEnabled = true;

    public function __construct(
        private readonly bool $enableCache = true,
        private readonly int $cacheTtl = 3600,
    ) {
    }

    /**
     * Create a new DataLoader
     *
     * @param string $key Loader key
     * @param Closure $batchLoadFn Batch load function
     * @return Closure Loader function
     */
    public function createLoader(string $key, Closure $batchLoadFn): Closure
    {
        $loader = new class($batchLoadFn, $this->enableCache, $this->cacheTtl) {
            protected array $queue = [];
            protected array $cache = [];
            protected bool $scheduled = false;
            protected Closure $batchLoadFn;
            protected bool $enableCache;
            protected int $cacheTtl;

            public function __construct(Closure $batchLoadFn, bool $enableCache, int $cacheTtl)
            {
                $this->batchLoadFn = $batchLoadFn;
                $this->enableCache = $enableCache;
                $this->cacheTtl = $cacheTtl;
            }

            public function load($key): mixed
            {
                // Check cache
                if ($this->enableCache && isset($this->cache[$key])) {
                    return $this->cache[$key];
                }

                // Add to queue
                if (!isset($this->queue[$key])) {
                    $this->queue[$key] = [];
                }

                // Create promise
                $promise = new \stdClass();
                $promise->resolved = false;
                $promise->value = null;
                $this->queue[$key][] = $promise;

                // Schedule batch load
                if (!$this->scheduled) {
                    $this->scheduled = true;
                    // Use next tick to batch requests
                    $this->scheduleBatchLoad();
                }

                return $promise;
            }

            protected function scheduleBatchLoad(): void
            {
                // Schedule batch load on next tick
                // In Swoole/Fiber environment, this would use event loop
                $this->dispatchBatchLoad();
            }

            protected function dispatchBatchLoad(): void
            {
                if (empty($this->queue)) {
                    $this->scheduled = false;
                    return;
                }

                $keys = array_keys($this->queue);
                $results = ($this->batchLoadFn)($keys);

                // Resolve promises
                foreach ($keys as $key) {
                    $value = $results[$key] ?? null;
                    
                    // Cache result
                    if ($this->enableCache) {
                        $this->cache[$key] = $value;
                    }

                    // Resolve promises
                    if (isset($this->queue[$key])) {
                        foreach ($this->queue[$key] as $promise) {
                            $promise->resolved = true;
                            $promise->value = $value;
                        }
                        unset($this->queue[$key]);
                    }
                }

                $this->scheduled = false;
            }

            public function clear($key = null): void
            {
                if ($key === null) {
                    $this->cache = [];
                } else {
                    unset($this->cache[$key]);
                }
            }
        };

        $this->loaders[$key] = $loader;

        return function ($key) use ($loader) {
            return $loader->load($key);
        };
    }

    /**
     * Get loader by key
     *
     * @param string $key
     * @return Closure|null
     */
    public function getLoader(string $key): ?Closure
    {
        return $this->loaders[$key] ?? null;
    }

    /**
     * Clear loader cache
     *
     * @param string $key Loader key (null to clear all)
     */
    public function clear(string $key = null): void
    {
        if ($key === null) {
            foreach ($this->loaders as $loader) {
                if (method_exists($loader, 'clear')) {
                    $loader->clear();
                }
            }
            $this->cache = [];
        } else {
            if (isset($this->loaders[$key]) && method_exists($this->loaders[$key], 'clear')) {
                $this->loaders[$key]->clear();
            }
            unset($this->cache[$key]);
        }
    }

    /**
     * Enable/disable batching
     *
     * @param bool $enabled
     */
    public function setBatchingEnabled(bool $enabled): void
    {
        $this->batchingEnabled = $enabled;
    }
}
