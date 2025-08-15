<?php

namespace App\Providers;

use Core\Support\ServiceProvider;
use Core\Translation\FileLoader;
use Illuminate\Translation\Translator;

class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('translator', function ($app) {
            // Sử dụng FileLoader và Filesystem của Core framework
            $loader = new FileLoader(
                $app->make(\Core\Filesystem\Filesystem::class),
                $app->basePath('lang'),
            );
            $translator = new Translator($loader, 'en'); // Default locale for registration

            return $translator;
        });
    }

    public function boot(): void
    {
        $this->app->get('translator')->setLocale(config('app.locale'));
        $this->app->get('translator')->setFallback(config('app.fallback_locale'));
    }
}
