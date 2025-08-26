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

class CacheResponseMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD'])) {
            return $handler->handle($request);
        }

        $route = $request->getAttribute('route');
        if (!$route || !is_array($route->handler)) {
            return $handler->handle($request);
        }

        try {
            $reflectionMethod = new ReflectionMethod(...$route->handler);
            $attributes = $reflectionMethod->getAttributes(Cache::class);
        } catch (\ReflectionException) {
            return $handler->handle($request);
        }

        if (empty($attributes)) {
            return $handler->handle($request);
        }

        /** @var Cache $cacheAttribute */
        $cacheAttribute = $attributes[0]->newInstance();

        $cacheKey = $this->generateCacheKey($request, $cacheAttribute);

        $cachedResponseData = $this->cache->get($cacheKey);
        if ($cachedResponseData) {
            $this->logger->debug("Cache hit for key: {$cacheKey}");
            /** @var ResponseInterface $response */
            $response = unserialize($cachedResponseData);
            return $response->withHeader('X-Cache', 'hit');
        }

        $this->logger->debug("Cache miss for key: {$cacheKey}");

        $response = $handler->handle($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->cache->set($cacheKey, serialize($response), $cacheAttribute->ttl);
            $this->logger->info("Response cached for key: {$cacheKey}", ['ttl' => $cacheAttribute->ttl]);
        }

        return $response->withHeader('X-Cache', 'miss');
    }

    private function generateCacheKey(ServerRequestInterface $request, Cache $attribute): string
    {
        if ($attribute->key) {
            return 'route_cache:' . $attribute->key;
        }

        return 'route_cache:' . sha1($request->getUri()->__toString());
    }
}
