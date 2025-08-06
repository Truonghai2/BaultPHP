<?php

namespace App\Providers;

use Core\CQRS\CommandBus;
use Core\CQRS\CommandBusWithMiddleware;
use Core\CQRS\Middleware\DatabaseTransactionMiddleware;
use Core\CQRS\Middleware\LoggingCommandMiddleware;
use Core\CQRS\SimpleCommandBus;
use Core\Support\ServiceProvider;

class CqrsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. Đăng ký SimpleCommandBus để nó có thể được inject vào decorator.
        $this->app->singleton(SimpleCommandBus::class);

        // 2. Đăng ký CommandBus chính của ứng dụng là decorator.
        $this->app->singleton(CommandBus::class, function ($app) {
            // Danh sách các middleware sẽ được áp dụng cho TẤT CẢ các command.
            $middleware = [
                // Middleware ngoài cùng: Mở/đóng transaction CSDL.
                DatabaseTransactionMiddleware::class,

                // Middleware lớp trong: Ghi log chi tiết về command.
                LoggingCommandMiddleware::class,
            ];

            return new CommandBusWithMiddleware(
                $app->make(SimpleCommandBus::class), // Bus được bọc bên trong
                $middleware,
                $app,
            );
        });
    }
}
