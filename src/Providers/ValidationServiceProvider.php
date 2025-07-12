<?php

namespace App\Providers;

use Http\FormRequest;
use Core\Support\ServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;

class ValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerTranslator();

        $this->app->singleton('validator', function ($app) {
            return new ValidationFactory($app['translator'], $app);
        });
    }

    public function boot(): void
    {
        // Đăng ký một hook để tự động chạy validation sau khi một FormRequest
        // được resolve từ container.
        $this->app->afterResolving(FormRequest::class, function ($formRequest) {
            /** @var FormRequest $formRequest */
            $formRequest->validateResolved();
        });
    }

    protected function registerTranslator(): void
    {
        $this->app->singleton('translator', function ($app) {
            // Đường dẫn tới thư mục chứa các file ngôn ngữ cho validation
            // Bạn có thể tải chúng từ: https://github.com/laravel/lang
            $langPath = resource_path('lang');

            $loader = new FileLoader(new Filesystem(), $langPath);
            $locale = env('APP_LOCALE', 'en');

            return new Translator($loader, $locale);
        });
    }
}