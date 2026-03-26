<?php

declare(strict_types=1);

namespace Core\Http\Deduplication;

use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Cache HTTP response cho deduplication.
 * Chỉ cache response 2xx; khi restore thêm header X-Cache-Hit.
 */
class ResponseCache
{
    private const DEFAULT_PREFIX = 'resp:';

    public function __construct(
        protected CacheInterface $cache,
        protected int $ttl = 60,
        protected string $keyPrefix = self::DEFAULT_PREFIX,
    ) {
    }

    public function get(string $signature): ?ResponseInterface
    {
        $key = $this->key($signature);
        try {
            $cached = $this->cache->get($key);
            if ($cached === null || !is_array($cached)) {
                return null;
            }
            return $this->deserializeResponse($cached);
        } catch (\Throwable) {
            return null;
        }
    }

    public function store(string $signature, ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return;
        }
        $key = $this->key($signature);
        try {
            $data = $this->serializeResponse($response);
            $this->cache->set($key, $data, $this->ttl);
        } catch (\Throwable) {
            // Ignore
        }
    }

    public function has(string $signature): bool
    {
        try {
            return $this->cache->has($this->key($signature));
        } catch (\Throwable) {
            return false;
        }
    }

    protected function key(string $signature): string
    {
        return $this->keyPrefix . $signature;
    }

    /**
     * @return array{status: int, headers: array<string, array<string>>, body: string, reason: string, cached_at: int}
     */
    protected function serializeResponse(ResponseInterface $response): array
    {
        return [
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => (string) $response->getBody(),
            'reason' => $response->getReasonPhrase(),
            'cached_at' => time(),
        ];
    }

    /**
     * @param array{status: int, headers: array<string, array<string>>, body: string, cached_at: int} $data
     */
    protected function deserializeResponse(array $data): ResponseInterface
    {
        $response = response($data['body'], $data['status']);

        foreach ($data['headers'] as $name => $values) {
            if (!is_array($values)) {
                $values = [$values];
            }
            foreach ($values as $value) {
                $response = $response->withAddedHeader($name, (string) $value);
            }
        }

        $response = $response->withHeader('X-Cache-Hit', 'dedup')
            ->withHeader('X-Cached-At', (string) ($data['cached_at'] ?? time()));

        return $response;
    }
}
