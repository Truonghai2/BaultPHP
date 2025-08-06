<?php

namespace App\Providers;

use Core\Redis\RedisManager;
use Core\Support\ServiceProvider;
use Redis;

class RedisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Đăng ký RedisManager như một singleton.
        $this->app->singleton('redis', function ($app) {
            $config = $app->make('config')->get('redis');
            return new RedisManager($app, $config);
        });

        // Bind RedisManager::class để có thể inject bằng type-hint.
        $this->app->alias('redis', RedisManager::class);

        // Bind \Redis::class để trả về kết nối mặc định.
        // Điều này giữ cho code cũ (nếu có) vẫn hoạt động và tiện lợi cho các trường hợp đơn giản.
        $this->app->bind(Redis::class, function ($app) {
            return $app->make('redis')->connection();
        });
    }
}
