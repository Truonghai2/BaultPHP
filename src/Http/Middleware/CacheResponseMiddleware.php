<?php

namespace Http\Middleware;

use Core\Routing\Attributes\Cache;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use ReflectionMethod;

/**
 * Middleware to automatically cache responses for routes annotated with #[Cache].
 */
class CacheResponseMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. Only cache GET and HEAD requests.
        if (!in_array($request->getMethod(), ['GET', 'HEAD'])) {
            return $handler->handle($request);
        }

        $route = $request->getAttribute('route');
        if (!$route || !is_array($route->handler)) {
            return $handler->handle($request);
        }

        // 2. Get the Cache attribute from the controller method using reflection.
        try {
            $reflectionMethod = new ReflectionMethod(...$route->handler);
            $attributes = $reflectionMethod->getAttributes(Cache::class);
        } catch (\ReflectionException) {
            // Method might not exist, let the router handle the error.
            return $handler->handle($request);
        }

        if (empty($attributes)) {
            return $handler->handle($request); // No Cache attribute, proceed normally.
        }

        /** @var Cache $cacheAttribute */
        $cacheAttribute = $attributes[0]->newInstance();

        // 3. Generate a unique cache key for this request.
        $cacheKey = $this->generateCacheKey($request, $cacheAttribute);

        // 4. Attempt to retrieve the response from the cache.
        $cachedResponseData = $this->cache->get($cacheKey);
        if ($cachedResponseData) {
            $this->logger->debug("Cache hit for key: {$cacheKey}");
            /** @var ResponseInterface $response */
            $response = unserialize($cachedResponseData);
            // Add a header to indicate a cache hit.
            return $response->withHeader('X-Cache', 'hit');
        }

        $this->logger->debug("Cache miss for key: {$cacheKey}");

        // 5. If not in cache, process the request to get the fresh response.
        $response = $handler->handle($request);

        // 6. Only cache successful (2xx) responses.
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            // Serialize the entire response object for storage.
            $this->cache->set($cacheKey, serialize($response), $cacheAttribute->ttl);
            $this->logger->info("Response cached for key: {$cacheKey}", ['ttl' => $cacheAttribute->ttl]);
        }

        // Add a header to indicate a cache miss.
        return $response->withHeader('X-Cache', 'miss');
    }

    /**
     * Generate a unique cache key for the request.
     */
    private function generateCacheKey(ServerRequestInterface $request, Cache $attribute): string
    {
        if ($attribute->key) {
            return 'route_cache:' . $attribute->key;
        }

        return 'route_cache:' . sha1($request->getUri()->__toString());
    }
}
