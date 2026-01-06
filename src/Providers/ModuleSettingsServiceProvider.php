<?php

namespace App\Providers;

use Core\BaseServiceProvider;
use Core\Module\ModuleSettingsManager;

class ModuleSettingsServiceProvider extends BaseServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleSettingsManager::class, function ($app) {
            $cache = $app->bound('cache') ? $app->make('cache') : null;
            $encrypter = $app->bound('encrypter') ? $app->make('encrypter') : null;

            return new ModuleSettingsManager($cache, $encrypter);
        });

        // Register alias
        $this->app->alias(ModuleSettingsManager::class, 'module.settings');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load helper functions
        $helperFile = $this->app->basePath('src/Core/helpers_module.php');
        if (file_exists($helperFile)) {
            require_once $helperFile;
        }
    }
}

