<?php

namespace Core;

use Core\Support\ServiceProvider;

/**
 * Class BaseServiceProvider
 *
 * A base service provider for modules to extend. It provides helper
 * methods for common module bootstrapping tasks like loading
 * configurations and migrations.
 *
 * @package Core
 */
class BaseServiceProvider extends ServiceProvider
{
    /**
     * The base path of the module.
     *
     * @var string|null
     */
    protected ?string $modulePath = null;

    /**
     * Register any application services.
     *
     * This method is intentionally left empty. Module-specific event-listener
     * mappings are loaded globally by the `EventServiceProvider` to avoid
     * redundant and potentially conflicting registrations.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Get the base path of the module.
     *
     * This method uses reflection to determine the directory of the child class,
     * assuming a standard module structure.
     *
     * @return string
     */
    protected function getModulePath(): string
    {
        if ($this->modulePath) {
            return $this->modulePath;
        }

        $reflector = new \ReflectionClass(static::class);
        // Assumes the provider is in `Modules/{ModuleName}/Providers/`
        return $this->modulePath = dirname($reflector->getFileName(), 2);
    }
}