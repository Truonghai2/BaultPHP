<?php

namespace Core\CQRS;

use Core\Application;
use Core\Support\Result;
use Psr\Log\LoggerInterface;

/**
 * Query Bus for CQRS pattern.
 * 
 * Dispatches queries to their handlers synchronously.
 * Optimized for read operations with caching support.
 * 
 * Usage:
 * ```php
 * $queryBus = app(QueryBus::class);
 * 
 * // Register handler
 * $queryBus->register(GetTodosQuery::class, GetTodosQueryHandler::class);
 * 
 * // Execute query
 * $result = $queryBus->execute(new GetTodosQuery(limit: 20));
 * 
 * if ($result->isSuccess()) {
 *     $todos = $result->getValue();
 * }
 * ```
 */
class QueryBus
{
    /**
     * Registered query handlers.
     * @var array<string, string>
     */
    protected array $handlers = [];

    /**
     * Middleware stack.
     * @var array<callable>
     */
    protected array $middleware = [];

    /**
     * Query result cache.
     * @var array<string, Result>
     */
    protected array $cache = [];

    /**
     * Enable query caching.
     */
    protected bool $cacheEnabled = false;

    public function __construct(
        protected Application $app,
        protected LoggerInterface $logger
    ) {}

    /**
     * Register a query handler.
     *
     * @param string $queryClass
     * @param string $handlerClass
     */
    public function register(string $queryClass, string $handlerClass): void
    {
        $this->handlers[$queryClass] = $handlerClass;
    }

    /**
     * Register multiple query handlers.
     *
     * @param array<string, string> $handlers
     */
    public function registerMany(array $handlers): void
    {
        foreach ($handlers as $queryClass => $handlerClass) {
            $this->register($queryClass, $handlerClass);
        }
    }

    /**
     * Add middleware to the pipeline.
     *
     * @param callable $middleware
     */
    public function addMiddleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Enable query result caching.
     *
     * @param bool $enabled
     */
    public function setCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;
    }

    /**
     * Execute a query.
     *
     * @param Query $query
     * @return Result<mixed>
     */
    public function execute(Query $query): Result
    {
        $queryClass = get_class($query);

        // Check cache first
        if ($this->cacheEnabled) {
            $cacheKey = $this->getCacheKey($query);
            if (isset($this->cache[$cacheKey])) {
                $this->logger->debug('QueryBus: Query result from cache', [
                    'query' => $query->getQueryName(),
                    'cache_key' => $cacheKey,
                ]);

                return $this->cache[$cacheKey];
            }
        }

        // Check if handler exists
        if (!isset($this->handlers[$queryClass])) {
            $error = "No handler registered for query: $queryClass";
            $this->logger->error('QueryBus: ' . $error, [
                'query' => $queryClass,
                'correlation_id' => $query->getCorrelationId(),
            ]);

            return Result::fail($error);
        }

        // Log query execution
        $this->logger->debug('QueryBus: Executing query', [
            'query' => $query->getQueryName(),
            'bounded_context' => $query->getBoundedContext(),
            'correlation_id' => $query->getCorrelationId(),
            'parameters' => $query->toArray(),
        ]);

        $startTime = microtime(true);

        try {
            // Resolve handler
            $handlerClass = $this->handlers[$queryClass];
            $handler = $this->app->make($handlerClass);

            // Execute through middleware pipeline
            $result = $this->executeWithMiddleware($handler, $query);

            // Cache successful result
            if ($this->cacheEnabled && $result->isSuccess()) {
                $cacheKey = $this->getCacheKey($query);
                $this->cache[$cacheKey] = $result;
            }

            // Log success
            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->debug('QueryBus: Query executed successfully', [
                'query' => $query->getQueryName(),
                'duration_ms' => round($duration, 2),
                'correlation_id' => $query->getCorrelationId(),
            ]);

            return $result;

        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            // Log failure
            $this->logger->error('QueryBus: Query execution failed', [
                'query' => $query->getQueryName(),
                'error' => $e->getMessage(),
                'duration_ms' => round($duration, 2),
                'correlation_id' => $query->getCorrelationId(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Result::fail($e->getMessage());
        }
    }

    /**
     * Execute query with middleware pipeline.
     *
     * @param QueryHandler $handler
     * @param Query $query
     * @return Result<mixed>
     */
    protected function executeWithMiddleware(QueryHandler $handler, Query $query): Result
    {
        if (empty($this->middleware)) {
            return $handler->handle($query);
        }

        // Build middleware pipeline
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($next, $middleware) => fn() => $middleware($query, $next),
            fn() => $handler->handle($query)
        );

        return $pipeline();
    }

    /**
     * Get cache key for query.
     *
     * @param Query $query
     * @return string
     */
    protected function getCacheKey(Query $query): string
    {
        return md5(serialize($query->toArray()));
    }

    /**
     * Clear query cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Get all registered handlers.
     *
     * @return array<string, string>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Check if a handler is registered for a query.
     *
     * @param string $queryClass
     * @return bool
     */
    public function hasHandler(string $queryClass): bool
    {
        return isset($this->handlers[$queryClass]);
    }
}
