<?php

namespace App\Providers;

use Core\Application;
use Core\Config;
use Core\Support\ServiceProvider;

class ConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('config', function ($app) {
            $items = [];
            $configPath = $app->basePath('config');

            if (is_dir($configPath)) {
                // This simple glob is sufficient for a flat config directory.
                foreach (glob($configPath . '/*.php') as $file) {
                    $key = basename($file, '.php');
                    $items[$key] = require $file;
                }
            }
            
            return new Config($items);
        });
    }
}