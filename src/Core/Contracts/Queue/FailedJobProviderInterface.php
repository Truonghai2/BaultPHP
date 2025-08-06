<?php

namespace Core\Contracts\Queue;

use Core\Support\Collection;
use Throwable;

/**
 * Interface FailedJobProviderInterface
 * Defines the contract for a failed job provider.
 */
interface FailedJobProviderInterface
{
    /**
     * Log a failed job into storage.
     *
     * @param string $connection The connection name on which the job failed.
     * @param string $queue The queue name on which the job failed.
     * @param string $payload The serialized payload of the job.
     * @param Throwable $exception The exception that caused the job to fail.
     * @return string|null The UUID of the failed job, or null on failure.
     */
    public function log(string $connection, string $queue, string $payload, Throwable $exception): ?string;

    /**
     * Get a list of all of the failed jobs.
     *
     * @return Collection
     */
    public function all(): Collection;

    /**
     * Find a failed job by its UUID.
     *
     * @param string $uuid
     * @return object|null A failed job object, or null if not found.
     */
    public function find(string $uuid): ?object;

    /**
     * Delete a single failed job from storage by its UUID.
     *
     * @param string $uuid
     * @return bool True if a job was deleted, false otherwise.
     */
    public function forget(string $uuid): bool;

    /**
     * Flush failed jobs from storage.
     *
     * @param string|null $queue Optionally flush only jobs from a specific queue.
     * @return int The number of jobs deleted.
     */
    public function flush(?string $queue = null): int;

    /**
     * Count the failed jobs.
     *
     * @param string|null $queue Optionally count only jobs from a specific queue.
     * @return int
     */
    public function count(?string $queue = null): int;
}
