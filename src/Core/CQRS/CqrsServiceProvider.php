<?php

namespace Core\CQRS;

use Core\Support\ServiceProvider;

/**
 * Registers the core CQRS components into the service container.
 */
class CqrsServiceProvider extends ServiceProvider
{
    /**
     * Register the command bus implementation.
     *
     * @return void
     */
    public function register(): void
    {
        // Register the concrete SimpleCommandBus as a singleton. This allows it to be
        // resolved directly, which is necessary for decoration.
        $this->app->singleton(SimpleCommandBus::class);

        // By default, alias the CommandBus interface to the concrete SimpleCommandBus.
        // This binding can be overridden by other providers (like a decorator) later
        // without causing circular dependency issues.
        $this->app->alias(SimpleCommandBus::class, CommandBus::class);
    }
}
