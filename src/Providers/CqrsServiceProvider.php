<?php

namespace App\Providers;

use Core\Contracts\Support\DeferrableProvider;
use Core\CQRS\Command\CommandBus;
use Core\CQRS\Command\Implementation\CommandBusWithMiddleware;
use Core\CQRS\Command\Implementation\SimpleCommandBus;
use Core\CQRS\Middleware\DatabaseTransactionMiddleware;
use Core\CQRS\Middleware\LoggingCommandMiddleware;
use Core\CQRS\Query\Implementation\QueryBusWithMiddleware as QueryBusWithMiddlewareImplementation;
use Core\CQRS\Query\Implementation\SimpleQueryBus as SimpleQueryBusImplementation;
use Core\CQRS\Query\QueryBus;
use Core\Support\ServiceProvider;

class CqrsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        // --- Command Bus Setup ---
        $this->app->singleton(SimpleCommandBus::class);

        $this->app->singleton(CommandBus::class, function ($app) {
            return new CommandBusWithMiddleware(
                $app->make(SimpleCommandBus::class),
                [
                    DatabaseTransactionMiddleware::class,
                    LoggingCommandMiddleware::class,
                ],
                $app,
            );
        });

        // --- Query Bus Setup ---
        $this->app->singleton(SimpleQueryBusImplementation::class);

        $this->app->singleton(QueryBus::class, function ($app) {
            // You can add query-specific middleware here, e.g., for caching or logging.
            return new QueryBusWithMiddlewareImplementation(
                $app->make(SimpleQueryBusImplementation::class),
                [
                    // LoggingQueryMiddleware::class,
                ],
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
        return [
            CommandBus::class,
            QueryBus::class,
        ];
    }
}
