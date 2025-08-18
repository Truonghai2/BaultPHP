<?php

namespace App\Providers;

use Core\Filesystem\Filesystem;
use Core\Support\ServiceProvider;
use Core\Translation\FileLoader;
use Illuminate\Translation\Translator;

class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('translator', function ($app) {
            // Sử dụng Core\Filesystem\Filesystem của chúng ta theo yêu cầu.
            // Điều quan trọng là class này phải có phương thức `getRequire()`,
            // mà chúng ta đã thêm vào để đảm bảo tương thích với trình tải bản dịch.
            $loader = new FileLoader(
                $app->make(Filesystem::class),
                $app->basePath('lang'),
            );

            $translator = new Translator($loader, config('app.locale'));

            return $translator;
        });
    }

    public function boot(): void
    {
        $this->app->get('translator')->setLocale(config('app.locale'));
        $this->app->get('translator')->setFallback(config('app.fallback_locale'));
    }
}
