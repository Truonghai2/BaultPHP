<?php

namespace App\Providers;

use Core\Support\ServiceProvider;
use Core\Validation\Factory as ValidationFactory;
use Http\FormRequest;

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

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // This callback ensures that whenever a FormRequest is resolved from the container
        // (e.g., via controller method injection), its validation logic is automatically executed.
        $this->app->afterResolving(FormRequest::class, fn(FormRequest $request) => $request->validateResolved());
    }
}