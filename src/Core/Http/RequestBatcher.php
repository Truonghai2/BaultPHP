<?php

declare(strict_types=1);

namespace Core\Http;

use Core\Support\Facades\Log;
use Psr\Http\Message\ServerRequestInterface;
use Swoole\Coroutine;

/**
 * Request Batching & Coalescing
 *
 * Batch multiple requests và execute in parallel.
 * Aggregate results efficiently.
 *
 * Features:
 * - Batch multiple requests
 * - Parallel execution
 * - Result aggregation
 * - Request coalescing
 */
class RequestBatcher
{
    protected array $batches = [];
    protected array $results = [];

    public function __construct(
        protected array $config = [],
    ) {
    }

    /**
     * Batch multiple requests
     *
     * @param array $requests Array of requests or callables
     * @param array $options Batch options
     * @return array Batched results
     */
    public function batch(array $requests, array $options = []): array
    {
        $parallel = $options['parallel'] ?? true;
        $timeout = $options['timeout'] ?? 30; // seconds
        $maxConcurrency = $options['max_concurrency'] ?? 10;

        if (empty($requests)) {
            return [];
        }

        if ($parallel && function_exists('Swoole\Coroutine\run')) {
            return $this->batchParallel($requests, $timeout, $maxConcurrency);
        }

        return $this->batchSequential($requests);
    }

    /**
     * Batch requests in parallel using Swoole coroutines
     */
    protected function batchParallel(array $requests, int $timeout, int $maxConcurrency): array
    {
        $results = [];
        $chunks = array_chunk($requests, $maxConcurrency);

        foreach ($chunks as $chunk) {
            $chunkResults = Coroutine\run(function () use ($chunk, $timeout) {
                $channels = [];
                
                foreach ($chunk as $index => $request) {
                    $channels[$index] = new Coroutine\Channel(1);
                    
                    Coroutine::create(function () use ($index, $request, $channels) {
                        try {
                            $result = $this->executeRequest($request);
                            $channels[$index]->push(['success' => true, 'result' => $result]);
                        } catch (\Throwable $e) {
                            $channels[$index]->push(['success' => false, 'error' => $e->getMessage()]);
                        }
                    });
                }

                $chunkResults = [];
                foreach ($channels as $index => $channel) {
                    $result = $channel->pop($timeout);
                    $chunkResults[$index] = $result ?? ['success' => false, 'error' => 'Timeout'];
                }

                return $chunkResults;
            });

            $results = array_merge($results, $chunkResults);
        }

        return $results;
    }

    /**
     * Batch requests sequentially
     */
    protected function batchSequential(array $requests): array
    {
        $results = [];

        foreach ($requests as $request) {
            try {
                $result = $this->executeRequest($request);
                $results[] = ['success' => true, 'result' => $result];
            } catch (\Throwable $e) {
                $results[] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Execute a single request
     */
    protected function executeRequest(mixed $request): mixed
    {
        if (is_callable($request)) {
            return $request();
        }

        if ($request instanceof ServerRequestInterface) {
            return $this->handleHttpRequest($request);
        }

        throw new \InvalidArgumentException("Invalid request type");
    }

    /**
     * Handle HTTP request
     */
    protected function handleHttpRequest(ServerRequestInterface $request): mixed
    {
        // In a real implementation, this would dispatch the request
        // For now, return a placeholder
        return [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'status' => 200,
        ];
    }

    /**
     * Coalesce similar requests
     *
     * Group similar requests together và execute once, sharing results.
     */
    public function coalesce(array $requests, callable $keyGenerator = null): array
    {
        if (!$keyGenerator) {
            $keyGenerator = fn($req) => $this->generateRequestKey($req);
        }

        $grouped = [];
        foreach ($requests as $index => $request) {
            $key = $keyGenerator($request);
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = ['index' => $index, 'request' => $request];
        }

        $results = [];
        foreach ($grouped as $key => $group) {
            // Execute first request in group
            $firstRequest = $group[0]['request'];
            $result = $this->executeRequest($firstRequest);

            // Share result with all requests in group
            foreach ($group as $item) {
                $results[$item['index']] = $result;
            }
        }

        // Sort by original index
        ksort($results);
        return array_values($results);
    }

    /**
     * Generate key for request coalescing
     */
    protected function generateRequestKey(mixed $request): string
    {
        if ($request instanceof ServerRequestInterface) {
            $method = $request->getMethod();
            $uri = $request->getUri()->getPath();
            $query = $request->getUri()->getQuery();
            return hash('md5', "{$method}:{$uri}:{$query}");
        }

        if (is_callable($request)) {
            // Use reflection to get function signature
            try {
                $reflection = new \ReflectionFunction($request);
                $file = $reflection->getFileName();
                $line = $reflection->getStartLine();
                return hash('md5', "{$file}:{$line}");
            } catch (\Throwable $e) {
                return hash('md5', serialize($request));
            }
        }

        return hash('md5', serialize($request));
    }

    /**
     * Batch database queries
     *
     * Execute multiple database queries in parallel.
     */
    public function batchQueries(array $queries, callable $executor): array
    {
        $requests = array_map(function ($query) use ($executor) {
            return fn() => $executor($query);
        }, $queries);

        return $this->batch($requests, ['parallel' => true]);
    }

    /**
     * Batch API calls
     *
     * Execute multiple API calls in parallel.
     */
    public function batchApiCalls(array $urls, callable $httpClient): array
    {
        $requests = array_map(function ($url) use ($httpClient) {
            return fn() => $httpClient($url);
        }, $urls);

        return $this->batch($requests, ['parallel' => true]);
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return [
            'batches_processed' => count($this->batches),
            'results_cached' => count($this->results),
        ];
    }
}
