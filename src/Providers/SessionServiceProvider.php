<?php

namespace App\Providers;

use Core\Database\Swoole\SwooleRedisPool;
use Core\Session\RedisSessionHandler;
use Core\Support\ServiceProvider;

class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Không cần đăng ký gì ở đây nữa, chúng ta sẽ xử lý trong boot().
    }

    public function boot(): void
    {
        // Chỉ thiết lập Redis handler nếu driver là 'redis' VÀ đang chạy trong môi trường Swoole.
        // Điều này đảm bảo các lệnh CLI không bị ảnh hưởng.
        $isSwooleEnv = extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0;

        if (config('session.driver') === 'redis' && $isSwooleEnv && SwooleRedisPool::isInitialized()) {
            $handler = new RedisSessionHandler(config('session'));

            // Đặt handler tùy chỉnh cho PHP
            session_set_save_handler($handler, true);

            // Cấu hình các tham số session
            ini_set('session.gc_probability', '0'); // Tắt GC mặc định của PHP
        }
        // Nếu không, framework sẽ sử dụng session handler mặc định của PHP (thường là 'files'),
        // hoặc một handler khác được cấu hình bởi một service provider khác.

        // Luôn bắt đầu session nếu nó chưa được bắt đầu
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
