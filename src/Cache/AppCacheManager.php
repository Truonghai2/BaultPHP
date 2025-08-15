<?php

namespace App\Cache;

use Core\Cache\CacheManager as BaseCacheManager;
use Core\Cache\Repository;
use Core\Cache\SwooleRedisCacheStore;
use Core\Database\Swoole\SwooleRedisPool;

class AppCacheManager extends BaseCacheManager
{
    /**
     * Ghi đè phương thức tạo Redis driver để nó tự động sử dụng
     * connection pool khi chạy trong môi trường Swoole.
     *
     * @param  array  $config
     * @return \Core\Cache\Repository Một repository chứa cache store phù hợp.
     */
    protected function createRedisDriver(array $config): Repository
    {
        // Kiểm tra xem có đang chạy trong môi trường Swoole và pool đã được bật/khởi tạo chưa.
        $isSwooleEnv = extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0;

        if ($isSwooleEnv && SwooleRedisPool::isInitialized()) {
            // Nếu có, trả về store được tối ưu cho Swoole.
            $swooleStore = new SwooleRedisCacheStore(
                $this->getPrefix($config),
            );
            return new Repository($swooleStore);
        }
        // Nếu không, gọi phương thức gốc để fallback về driver Redis thông thường.
        // Điều này đảm bảo các lệnh CLI (ví dụ: cache:clear) vẫn hoạt động.
        return parent::createRedisDriver($config);
    }
}
