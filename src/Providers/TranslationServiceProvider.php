<?php

namespace App\Providers;

use Core\ServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('translator', function ($app) {
            $loader = new FileLoader(new Filesystem(), $app->basePath('lang'));

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