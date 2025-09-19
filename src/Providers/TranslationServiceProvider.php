<?php

namespace App\Providers;

use Core\FileSystem\Filesystem;
use Core\Support\ServiceProvider;
use Core\Translation\FileLoader;
use Illuminate\Translation\Translator;

class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('translator', function ($app) {
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

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return ['translator', Translator::class];
    }
}
