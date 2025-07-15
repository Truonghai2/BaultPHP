<?php

namespace App\Providers;

use Core\Support\ServiceProvider;

class ConsoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Logic đăng ký lệnh đã được chuyển hoàn toàn sang ConsoleKernel
        // để tự động hóa và loại bỏ việc bảo trì thủ công.
        // ConsoleKernel sẽ quét các thư mục và sử dụng DI container
        // để khởi tạo các lệnh, cho phép inject dependency.
        //
        // Bằng cách này, chúng ta chỉ cần tạo file command mới và nó sẽ
        // tự động được nhận diện mà không cần phải khai báo ở đây.

        $this->app->singleton(\App\Console\Kernel::class);
    }
}