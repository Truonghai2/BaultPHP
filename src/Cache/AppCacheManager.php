<?php

namespace App\Cache;

use Core\Cache\CacheManager as BaseCacheManager;
use Core\Cache\Repository;
use Core\Cache\SwooleRedisCacheStore;
use Core\Database\Swoole\SwooleRedisPool;

class AppCacheManager extends BaseCacheManager
{
    protected function createRedisDriver(array $config): Repository
    {
        $isSwooleEnv = extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0;

        if ($isSwooleEnv && SwooleRedisPool::isInitialized()) {
            $swooleStore = new SwooleRedisCacheStore(
                $this->getPrefix($config),
            );
            return new Repository($swooleStore);
        }
        return parent::createRedisDriver($config);
    }
}
