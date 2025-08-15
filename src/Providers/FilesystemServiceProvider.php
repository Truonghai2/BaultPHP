<?php

namespace App\Providers;

use Core\Filesystem\Filesystem;
use Core\Support\ServiceProvider;

class FilesystemServiceProvider extends ServiceProvider
{
    /**
     * Register the filesystem services.
     *
     * @return void
     */
    public function register(): void
    {
        // Đăng ký Core\Filesystem\Filesystem như một singleton.
        // Điều này đảm bảo toàn bộ ứng dụng chỉ sử dụng một instance duy nhất,
        // giúp tiết kiệm tài nguyên và đảm bảo tính nhất quán.
        $this->app->singleton(Filesystem::class, function () {
            return new Filesystem();
        });

        // Tạo một alias 'files' để có thể resolve bằng app('files') nếu cần,
        // và cũng để tương thích với các phần khác có thể đang dùng alias này.
        $this->app->alias(Filesystem::class, 'files');
    }
}
