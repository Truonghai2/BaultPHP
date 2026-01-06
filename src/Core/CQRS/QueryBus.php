<?php

namespace Core\CQRS;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Core\CQRS\Contracts\QueryInterface;
use Core\CQRS\Middleware\MiddlewareInterface;

/**
 * Query Bus
 * 
 * Dispatches queries to their handlers.
 * Optimized for read operations.
 */
class QueryBus
{
    /**
     * @var array<string, string> Query => Handler mapping
     */
    private array $handlers = [];

    /**
     * @var MiddlewareInterface[]
     */
    private array $middleware = [];

    /**
     * Register a query handler
     */
    public function register(string $queryClass, string $handlerClass): void
    {
        $this->handlers[$queryClass] = $handlerClass;
    }

    /**
     * Add middleware to the pipeline
     */
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Execute a query
     */
    public function execute(QueryInterface $query): mixed
    {
        $queryClass = get_class($query);

        if (!isset($this->handlers[$queryClass])) {
            throw new \RuntimeException("No handler registered for query: {$queryClass}");
        }

        $handlerClass = $this->handlers[$queryClass];
        $handler = app($handlerClass);

        if (!$handler instanceof QueryHandlerInterface) {
            throw new \RuntimeException("Handler must implement QueryHandlerInterface");
        }

        // Build middleware pipeline
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($next, $middleware) => fn($qry) => $middleware->handle($qry, $next),
            fn($qry) => $handler->handle($qry)
        );

        return $pipeline($query);
    }
}

