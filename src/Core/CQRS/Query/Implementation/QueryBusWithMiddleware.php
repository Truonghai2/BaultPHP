<?php

declare(strict_types=1);

namespace Core\CQRS\Query\Implementation;

use Core\Application;
use Core\CQRS\Middleware\MiddlewareInterface;
use Core\CQRS\Query\Query;
use Core\CQRS\Query\QueryBus;

/**
 * QueryBusWithMiddleware is a query bus that applies middleware to queries before dispatching them.
 * It allows for additional processing, such as logging, caching, or performance monitoring.
 * 
 * Optimized with middleware instance caching.
 */
class QueryBusWithMiddleware implements QueryBus
{
    /**
     * @var array<string, MiddlewareInterface> Cache for resolved middleware instances
     */
    private array $middlewareInstances = [];

    /**
     * @param SimpleQueryBus $decoratedBus The inner query bus that will ultimately execute the handler.
     * @param array<class-string<MiddlewareInterface>> $middleware The array of middleware classes to apply.
     * @param Application $app The application container to resolve middleware instances.
     */
    public function __construct(
        private readonly SimpleQueryBus $decoratedBus,
        private readonly array $middleware,
        private readonly Application $app,
    ) {
    }

    /**
     * Delegate handler registration to the inner bus.
     */
    public function register(string $queryClass, string $handlerClass): void
    {
        $this->decoratedBus->register($queryClass, $handlerClass);
    }

    /**
     * Delegate handler mapping to the inner bus.
     *
     * @param array<string, string> $map
     */
    public function map(array $map): void
    {
        $this->decoratedBus->map($map);
    }

    /**
     * Dispatch the query through the middleware pipeline.
     */
    public function dispatch(Query $query): mixed
    {
        if (empty($this->middleware)) {
            return $this->decoratedBus->dispatch($query);
        }

        $coreDispatch = fn (Query $q) => $this->decoratedBus->dispatch($q);

        $pipeline = array_reduce(
            array_reverse($this->middleware),
            $this->createNext(),
            $coreDispatch,
        );

        return $pipeline($query);
    }

    /**
     * Create a callable that wraps the next middleware in the pipeline.
     * 
     * Optimized with middleware instance caching.
     *
     * @return callable
     */
    private function createNext(): callable
    {
        return function (callable $stack, string $pipe) {
            return function (Query $query) use ($stack, $pipe) {
                // Cache middleware instances for better performance
                if (!isset($this->middlewareInstances[$pipe])) {
                    $this->middlewareInstances[$pipe] = $this->app->make($pipe);
                }

                return $this->middlewareInstances[$pipe]->handle($query, $stack);
            };
        };
    }

    /**
     * Clear all cached middleware instances (useful for testing).
     */
    public function clearCache(): void
    {
        $this->middlewareInstances = [];
        $this->decoratedBus->clearCache();
    }
}
