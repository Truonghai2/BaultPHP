<?php

namespace Modules\Todo\Providers;

use Core\ServiceProvider;
use Core\CQRS\{CommandBus, QueryBus};
use Modules\Todo\Application\Commands\{CreateTodoCommand, CompleteTodoCommand};
use Modules\Todo\Application\CommandHandlers\{CreateTodoCommandHandler, CompleteTodoCommandHandler};
use Modules\Todo\Application\Queries\GetTodosQuery;
use Modules\Todo\Application\QueryHandlers\GetTodosQueryHandler;
use Modules\Todo\Application\EventHandlers\{TodoCreatedEventHandler, TodoCompletedEventHandler};

/**
 * Todo Module Service Provider.
 * 
 * Registers CQRS handlers and event listeners.
 */
class TodoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register repositories
        $this->app->singleton(
            \Modules\Todo\Infrastructure\Repositories\TodoWriteRepository::class
        );
        
        $this->app->singleton(
            \Modules\Todo\Infrastructure\Repositories\TodoReadRepository::class
        );
    }

    public function boot(): void
    {
        $this->registerCommandHandlers();
        $this->registerQueryHandlers();
        $this->registerEventHandlers();
        $this->registerRoutes();
    }

    /**
     * Register command handlers with CommandBus.
     */
    protected function registerCommandHandlers(): void
    {
        $commandBus = $this->app->make(CommandBus::class);

        $commandBus->registerMany([
            CreateTodoCommand::class => CreateTodoCommandHandler::class,
            CompleteTodoCommand::class => CompleteTodoCommandHandler::class,
        ]);
    }

    /**
     * Register query handlers with QueryBus.
     */
    protected function registerQueryHandlers(): void
    {
        $queryBus = $this->app->make(QueryBus::class);

        $queryBus->registerMany([
            GetTodosQuery::class => GetTodosQueryHandler::class,
        ]);
    }

    /**
     * Register event handlers for eventual consistency.
     */
    protected function registerEventHandlers(): void
    {
        $events = $this->app->make('events');

        // When Todo is created, update read model
        $events->listen('todo.created', TodoCreatedEventHandler::class);

        // When Todo is completed, update read model
        $events->listen('todo.completed', TodoCompletedEventHandler::class);

        // When Todo is uncompleted, update read model
        $events->listen('todo.uncompleted', TodoCompletedEventHandler::class);
    }

    /**
     * Register module routes.
     */
    protected function registerRoutes(): void
    {
        $router = $this->app->make('router');

        $router->group(['prefix' => 'api/todos', 'middleware' => ['auth']], function ($router) {
            $router->get('/', [\Modules\Todo\Http\Controllers\TodoController::class, 'index']);
            $router->post('/', [\Modules\Todo\Http\Controllers\TodoController::class, 'store']);
            $router->post('/{id}/complete', [\Modules\Todo\Http\Controllers\TodoController::class, 'complete']);
        });
    }
}
