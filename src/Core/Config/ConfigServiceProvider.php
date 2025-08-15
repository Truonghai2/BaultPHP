<?php

namespace Core\Config;

use Core\Support\ServiceProvider;
use Symfony\Component\Finder\Finder;

class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('config', function ($app) {
            return new Repository();
        });
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        /** @var Repository $config */
        $config = $this->app->make('config');

        $configPath = $this->app->basePath('config');

        if (!is_dir($configPath)) {
            return;
        }

        // Using Symfony Finder for a more robust file discovery
        $files = Finder::create()->files()->in($configPath)->name('*.php');

        foreach ($files as $file) {
            $key = $file->getBasename('.php');
            $config->set($key, require $file->getRealPath());
        }
    }
}
