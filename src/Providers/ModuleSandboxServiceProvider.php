<?php

declare(strict_types=1);

namespace App\Providers;

use App\Cache\SandboxAwareCacheManager;
use Core\Module\Sandbox\ModulePermissionGate;
use Core\Module\Sandbox\ModulePermissionRegistry;
use Core\Support\ServiceProvider;

/**
 * Registers module sandbox services and, when enabled, wraps cache (and
 * optionally DB/HTTP) so that code running in a module context can only
 * perform actions allowed by that module's manifest "permissions".
 */
class ModuleSandboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModulePermissionRegistry::class);
        $this->app->singleton(ModulePermissionGate::class, function ($app) {
            return new ModulePermissionGate($app->make(ModulePermissionRegistry::class));
        });

        if ($this->app->make('config')->get('module_sandbox.enabled', false)
            && $this->app->make('config')->get('module_sandbox.enforce_cache', true)) {
            $this->app->singleton('cache.factory', function ($app) {
                return new SandboxAwareCacheManager(
                    $app,
                    $app->make(ModulePermissionGate::class),
                );
            });
        }
    }
}
