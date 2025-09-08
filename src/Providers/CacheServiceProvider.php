<?php

namespace App\Providers;

use App\Cache\AppCacheManager;
use Core\Cache\CacheManager; // Giữ lại để alias
use Core\Support\ServiceProvider;
use Psr\SimpleCache\CacheInterface;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. Đăng ký CacheManager như một singleton. Nó là nhà máy (factory)
        // cho tất cả các cache store.
        $this->app->singleton('cache', function ($app) {
            return new AppCacheManager($app);
        });

        // 2. Tạo alias để có thể inject Core\Cache\CacheManager bằng type-hint nếu cần.
        $this->app->alias('cache', CacheManager::class);

        $this->app->singleton(\Core\Contracts\Cache\Factory::class, \App\Cache\AppCacheManager::class);

        // 3. Binding quan trọng nhất: Bind interface PSR-16 vào cache store mặc định.
        // Điều này cho phép code của bạn chỉ cần yêu cầu CacheInterface và container sẽ
        // tự động cung cấp store mặc định (ví dụ: redis hoặc file).
        $this->app->singleton(CacheInterface::class, function ($app) {
            return $app->make('cache')->store();
        });
    }
}
