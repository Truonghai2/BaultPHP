<?php

namespace Core\CQRS\Query\Implementation;

use Core\Application;
use Core\CQRS\HandlerLocator;
use Core\CQRS\Query\Query;
use Core\CQRS\Query\QueryBus;
use InvalidArgumentException;

/**
 * A basic implementation of a query bus that maps queries to their handlers.
 */
class SimpleQueryBus implements QueryBus
{
    use HandlerLocator;

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
     * @throws InvalidArgumentException If no handler is found for the query.
     */
    public function dispatch(Query $query): mixed
    {
        $queryClass = $query::class;
        $handlerClass = $this->findHandler($queryClass, 'query');

        $handler = $this->app->make($handlerClass);

        return $handler->handle($query);
    }
}
