<?php

namespace App\Providers;

use Core\Support\ServiceProvider;
use Core\Validation\Factory as ValidationFactory;

class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Register the validation services.
     *
     * @return void
     */
    public function register(): void
    {
        // Đăng ký ValidationFactory như một singleton. Đây là binding gốc.
        $this->app->singleton(ValidationFactory::class, function ($app) {
            return new ValidationFactory($app);
        });

        // Tạo một alias từ tên ngắn 'validator' đến tên class đầy đủ.
        // Điều này cho phép resolve service thông qua app('validator').
        $this->app->alias(ValidationFactory::class, 'validator');
    }
}
