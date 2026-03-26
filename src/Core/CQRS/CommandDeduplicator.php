<?php

namespace Core\CQRS;

use Psr\SimpleCache\CacheInterface;

/**
 * Deduplicate CQRS Commands.
 * 
 * Prevents duplicate command execution based on command ID.
 * Critical for idempotency in distributed systems.
 */
class CommandDeduplicator
{
    public function __construct(
        protected CacheInterface $cache,
        protected int $ttl = 300, // 5 minutes
    ) {
    }

    /**
     * Execute command with deduplication.
     * 
     * @param Command $command
     * @param callable $handler Command handler
     * @return mixed Result from handler
     */
    public function execute(Command $command, callable $handler): mixed
    {
        $commandId = $command->getCorrelationId();
        $cacheKey = "cmd:dedup:{$commandId}";

        // Check if command was already executed
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $this->deserializeResult($cached);
        }

        // Execute command
        $result = $handler($command);

        // Cache result
        $this->cache->set($cacheKey, $this->serializeResult($result), $this->ttl);

        return $result;
    }

    /**
     * Check if command was already executed.
     * 
     * @param string $commandId Command correlation ID
     * @return bool True if already executed
     */
    public function wasExecuted(string $commandId): bool
    {
        return $this->cache->has("cmd:dedup:{$commandId}");
    }

    /**
     * Serialize command result.
     * 
     * @param mixed $result
     * @return array
     */
    protected function serializeResult(mixed $result): array
    {
        return [
            'result' => serialize($result),
            'executed_at' => time(),
        ];
    }

    /**
     * Deserialize command result.
     * 
     * @param array $data
     * @return mixed
     */
    protected function deserializeResult(array $data): mixed
    {
        return unserialize($data['result']);
    }
}
