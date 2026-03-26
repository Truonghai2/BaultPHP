<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Module\Composer\ComposerModuleDiscovery;
use Core\Module\ModulePathResolver;
use Core\Support\ServiceProvider;

/**
 * Registers Composer-based module discovery (packages with type "bault-module" or extra.bault.module).
 */
class ComposerModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ComposerModuleDiscovery::class);
        $this->app->singleton(ModulePathResolver::class);
    }
}
