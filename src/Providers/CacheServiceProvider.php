<?php

namespace App\Providers;

use Core\Cache\AIPredictiveCache;
use Core\Cache\CacheManager;
use Core\Cache\CacheManager as CoreCacheManager;
use Core\Cache\CrdtCache;
use Core\Cache\MultiTierCacheManager;
use Core\Cache\Repository;
use Core\Support\ServiceProvider;
use Psr\SimpleCache\CacheInterface;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('cache.factory', function ($app) {
            return new CoreCacheManager($app);
        });

        $this->app->singleton('cache', function ($app) {
            return $app->make('cache.factory');
        });

        $this->app->alias('cache', CacheManager::class);

        $this->app->singleton(\Core\Contracts\Cache\Factory::class, \App\Cache\AppCacheManager::class);

        $this->app->singleton(CacheInterface::class, function ($app) {
            return $app->make('cache')->store();
        });

        $this->registerAdvancedCache();
    }

    /**
     * Register advanced caching strategies
     */
    protected function registerAdvancedCache(): void
    {
        $config = $this->app->make('config');
        $advancedConfig = $config->get('cache-advanced', []);

        // Register Multi-Tier Cache Manager
        if ($advancedConfig['multi_tier']['enabled'] ?? false) {
            $this->app->singleton(MultiTierCacheManager::class, function ($app) use ($advancedConfig) {
                $l2Cache = $app->make('cache')->store();
                
                // L1 Cache (APCu or Array)
                $l1Cache = null;
                if ($advancedConfig['multi_tier']['l1']['enabled'] ?? false) {
                    if (function_exists('apcu_fetch')) {
                        $l1Cache = new class implements CacheInterface {
                            public function get(string $key, mixed $default = null): mixed
                            {
                                $value = apcu_fetch($key, $success);
                                return $success ? $value : $default;
                            }
                            public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
                            {
                                return apcu_store($key, $value, is_int($ttl) ? $ttl : 60);
                            }
                            public function delete(string $key): bool { return apcu_delete($key); }
                            public function clear(): bool { return apcu_clear_cache(); }
                            public function getMultiple(iterable $keys, mixed $default = null): iterable
                            {
                                $results = [];
                                foreach ($keys as $key) {
                                    $results[$key] = $this->get($key, $default);
                                }
                                return $results;
                            }
                            public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
                            {
                                foreach ($values as $key => $value) {
                                    $this->set($key, $value, $ttl);
                                }
                                return true;
                            }
                            public function deleteMultiple(iterable $keys): bool
                            {
                                foreach ($keys as $key) {
                                    $this->delete($key);
                                }
                                return true;
                            }
                            public function has(string $key): bool { return apcu_exists($key); }
                        };
                    }
                }

                // L3 Cache (File or Database)
                $l3Cache = null;
                if ($advancedConfig['multi_tier']['l3']['enabled'] ?? false) {
                    $l3Driver = $advancedConfig['multi_tier']['l3']['driver'] ?? 'file';
                    if ($l3Driver === 'file') {
                        $l3Cache = $app->make('cache')->store('file');
                    }
                }

                return new MultiTierCacheManager(
                    $l2Cache,
                    $l1Cache,
                    $l3Cache,
                    $advancedConfig
                );
            });
        }

        // Register AI Predictive Cache
        if ($advancedConfig['predictive']['enabled'] ?? false) {
            $this->app->singleton(AIPredictiveCache::class, function ($app) use ($advancedConfig) {
                $baseCache = $app->make('cache')->store();
                return new AIPredictiveCache(
                    $baseCache,
                    null,
                    $advancedConfig['predictive'] ?? []
                );
            });
        }

        // Register CRDT Cache
        if ($advancedConfig['crdt']['enabled'] ?? false) {
            $this->app->singleton(CrdtCache::class, function ($app) use ($advancedConfig) {
                $baseCache = $app->make('cache')->store();
                
                // Parse replicas from config
                $replicas = [];
                if (!empty($advancedConfig['crdt']['replicas'])) {
                    $replicas = is_string($advancedConfig['crdt']['replicas'])
                        ? array_filter(array_map('trim', explode(',', $advancedConfig['crdt']['replicas'])))
                        : $advancedConfig['crdt']['replicas'];
                }

                $crdtConfig = array_merge($advancedConfig['crdt'] ?? [], [
                    'replicas' => $replicas,
                    'node_id' => $advancedConfig['crdt']['node_id'] ?? gethostname() . '_' . getmypid(),
                ]);

                return new CrdtCache($baseCache, null, $crdtConfig);
            });
        }
    }
}
