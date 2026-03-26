<?php

namespace Modules\Todo\Infrastructure\Repositories;

use Core\Database\Connection;
use Core\Cache\Repository as CacheRepository;
use Modules\Todo\Infrastructure\ReadModels\TodoReadModel;

/**
 * Read Repository for Todo Read Model.
 * 
 * Optimized for queries with caching.
 */
class TodoReadRepository
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private Connection $db,
        private CacheRepository $cache
    ) {}

    /**
     * Get todos by user ID.
     */
    public function getByUserId(
        string $userId,
        ?int $limit = 20,
        ?int $offset = 0,
        ?bool $completed = null
    ): array {
        $cacheKey = "todos:user:$userId:" . ($completed ?? 'all');

        // Try cache first
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return array_map(
                fn($data) => TodoReadModel::fromArray($data),
                array_slice($cached, $offset, $limit)
            );
        }

        // Query database
        $query = $this->db->table('todos')
            ->where('user_id', $userId);

        if ($completed !== null) {
            $query->where('completed', $completed);
        }

        $results = $query
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $todos = array_map(
            fn($row) => TodoReadModel::fromArray((array) $row),
            $results
        );

        // Cache for future queries
        $this->cache->put(
            $cacheKey,
            array_map(fn($todo) => $todo->toArray(), $todos),
            self::CACHE_TTL
        );

        return $todos;
    }

    /**
     * Find todo by ID.
     */
    public function findById(string $id): ?TodoReadModel
    {
        $cacheKey = "todo:$id";

        // Try cache
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return TodoReadModel::fromArray($cached);
        }

        // Query database
        $data = $this->db->table('todos')
            ->where('id', $id)
            ->first();

        if (!$data) {
            return null;
        }

        $todo = TodoReadModel::fromArray((array) $data);

        // Cache
        $this->cache->put($cacheKey, $todo->toArray(), self::CACHE_TTL);

        return $todo;
    }

    /**
     * Get todo count by user.
     */
    public function countByUserId(string $userId, ?bool $completed = null): int
    {
        $query = $this->db->table('todos')
            ->where('user_id', $userId);

        if ($completed !== null) {
            $query->where('completed', $completed);
        }

        return $query->count();
    }

    /**
     * Clear cache for a user.
     */
    public function clearCacheForUser(string $userId): void
    {
        $this->cache->forget("todos:user:$userId:all");
        $this->cache->forget("todos:user:$userId:true");
        $this->cache->forget("todos:user:$userId:false");
    }

    /**
     * Clear cache for a specific todo.
     */
    public function clearCacheForTodo(string $todoId): void
    {
        $this->cache->forget("todo:$todoId");
    }
}
