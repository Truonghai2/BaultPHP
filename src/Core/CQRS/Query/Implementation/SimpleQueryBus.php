<?php

namespace Core\CQRS\Query\Implementation;

use Core\Application;
use Core\CQRS\HandlerLocator;
use Core\CQRS\Query\Query;
use Core\CQRS\Query\QueryBus;
use InvalidArgumentException;

/**
 * A basic implementation of a query bus that maps queries to their handlers.
 * 
 * Optimized with handler caching and improved error handling.
 */
class SimpleQueryBus implements QueryBus
{
    use HandlerLocator;

    /**
     * @var array<string, object> Cache for resolved handler instances
     */
    private array $handlerInstances = [];

    public function __construct(private readonly Application $app)
    {
    }

    /**
     * Register a query-to-handler mapping.
     *
     * @param string $queryClass The fully qualified class name of the query.
     * @param string $handlerClass The fully qualified class name of the handler.
     */
    public function register(string $queryClass, string $handlerClass): void
    {
        $this->handlers[$queryClass] = $handlerClass;
        // Clear instance cache when registering new handlers
        unset($this->handlerInstances[$queryClass]);
    }

    /**
     * Register a map of queries to their handlers.
     *
     * @param array<class-string<Query>, class-string> $map
     */
    public function map(array $map): void
    {
        foreach ($map as $queryClass => $handlerClass) {
            $this->register($queryClass, $handlerClass);
        }
    }

    /**
     * Dispatch the query to its handler.
     *
     * @param Query $query
     * @return mixed
     * @throws InvalidArgumentException If no handler is found for the query.
     */
    public function dispatch(Query $query): mixed
    {
        $queryClass = $query::class;
        $handlerClass = $this->findHandler($queryClass, 'query');

        // Cache handler instances for better performance
        if (!isset($this->handlerInstances[$handlerClass])) {
            $this->handlerInstances[$handlerClass] = $this->app->make($handlerClass);
        }

        $handler = $this->handlerInstances[$handlerClass];

        return $handler->handle($query);
    }

    /**
     * Clear all cached handler instances (useful for testing).
     */
    public function clearCache(): void
    {
        $this->handlerInstances = [];
        $this->clearHandlerCache();
    }
}
