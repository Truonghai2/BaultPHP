<?php

namespace App\Providers;

use Core\Contracts\Support\DeferrableProvider;
use Core\CQRS\CommandBus;
use Core\CQRS\CommandBusWithMiddleware;
use Core\CQRS\Middleware\DatabaseTransactionMiddleware;
use Core\CQRS\Middleware\LoggingCommandMiddleware;
use Core\CQRS\SimpleCommandBus;
use Core\Support\ServiceProvider;

class CqrsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(SimpleCommandBus::class);

        $this->app->singleton(CommandBus::class, function ($app) {
            // Resolve middleware instances from the container to handle dependencies correctly.
            $middlewareInstances = [
                $app->make(DatabaseTransactionMiddleware::class),
                $app->make(LoggingCommandMiddleware::class),
            ];

            return new CommandBusWithMiddleware(
                $app->make(SimpleCommandBus::class),
                $middlewareInstances,
                $app,
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [CommandBus::class];
    }
}
