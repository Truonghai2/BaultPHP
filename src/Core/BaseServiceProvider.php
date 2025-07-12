<?php

namespace Core;

use Core\Support\ServiceProvider;

abstract class BaseServiceProvider extends ServiceProvider
{
    protected string $modulePath;

    public function register(): void
    {
        $this->modulePath = dirname(__DIR__, 2) . '/modules/' . $this->getModuleName();

        $this->registerConfig();
        $this->registerPermissions();
        $this->registerEvents();
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadMigrations();
    }

    protected function getModuleName(): string
    {
        // Get module name from namespace: Modules\User\Providers > User
        $parts = explode('\\', static::class);
        return $parts[1] ?? 'Unknown';
    }

    protected function loadRoutes(): void
    {
        $path = $this->modulePath . '/Http/routes.php';
        if (file_exists($path)) {
            $this->loadRoutesFrom($path);
        }
    }

    protected function loadMigrations(): void
    {
        $path = $this->modulePath . '/Infrastructure/Migrations';
        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }

    protected function registerPermissions(): void
    {
        $file = $this->modulePath . '/permissions.php';
        if (file_exists($file)) {
            PermissionRegistrar::register($file);
        }
    }

    protected function registerEvents(): void
    {
        $file = $this->modulePath . '/events.php';
        if (file_exists($file)) {
            /** @var \Core\Events\Dispatcher $dispatcher */
            $dispatcher = $this->app->make('events');
            $eventsMap = require $file;

            foreach ($eventsMap as $event => $listeners) {
                foreach ((array) $listeners as $listener) {
                    $dispatcher->listen($event, $listener);
                }
            }
        }
    }

    protected function registerConfig(): void
    {
        $file = $this->modulePath . '/config.php';
        if (file_exists($file)) {
            $this->mergeConfigFrom($file, strtolower($this->getModuleName()));
        }
    }
}
