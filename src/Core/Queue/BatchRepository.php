<?php

namespace Core\Queue;

use Core\Application;

/**
 * Repository for storing and retrieving batch information.
 * 
 * Uses Redis for fast access and automatic expiration.
 */
class BatchRepository
{
    protected \Redis $redis;
    protected string $prefix = 'batch:';
    protected int $ttl = 86400; // 24 hours

    public function __construct(protected Application $app)
    {
        $manager = $this->app->make('redis');
        $this->redis = $manager->connection('default');
    }

    /**
     * Store a batch.
     */
    public function store(Batch $batch): void
    {
        $key = $this->getKey($batch->id());
        $data = json_encode($batch->toArray());
        
        $this->redis->setEx($key, $this->ttl, $data);
    }

    /**
     * Find a batch by ID.
     */
    public function find(string $batchId): ?Batch
    {
        $key = $this->getKey($batchId);
        $data = $this->redis->get($key);

        if (!$data) {
            return null;
        }

        $array = json_decode($data, true);
        return Batch::fromArray($this->app, $array);
    }

    /**
     * Delete a batch.
     */
    public function delete(string $batchId): void
    {
        $key = $this->getKey($batchId);
        $this->redis->del($key);
    }

    /**
     * Get all batch IDs.
     */
    public function all(): array
    {
        $keys = $this->redis->keys($this->prefix . '*');
        $batches = [];

        foreach ($keys as $key) {
            $data = $this->redis->get($key);
            if ($data) {
                $array = json_decode($data, true);
                $batches[] = Batch::fromArray($this->app, $array);
            }
        }

        return $batches;
    }

    /**
     * Prune finished batches older than the given number of hours.
     */
    public function prune(int $hours = 24): int
    {
        $keys = $this->redis->keys($this->prefix . '*');
        $pruned = 0;
        $threshold = time() - ($hours * 3600);

        foreach ($keys as $key) {
            $data = $this->redis->get($key);
            if ($data) {
                $array = json_decode($data, true);
                if (isset($array['finished_at']) && $array['finished_at'] < $threshold) {
                    $this->redis->del($key);
                    $pruned++;
                }
            }
        }

        return $pruned;
    }

    /**
     * Get the Redis key for a batch.
     */
    protected function getKey(string $batchId): string
    {
        return $this->prefix . $batchId;
    }
}
