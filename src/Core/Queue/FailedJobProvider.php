<?php

namespace Core\Queue;

use Core\Contracts\Queue\FailedJobProviderInterface;
use Core\Support\Collection;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Class FailedJobProvider
 * Provides an abstraction layer for interacting with failed jobs storage.
 * @implements FailedJobProviderInterface
 */
class FailedJobProvider implements FailedJobProviderInterface
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
    public function log(string $connection, string $queue, string $payload, Throwable $exception): ?string
    {
        try {
            $uuid = Uuid::uuid4()->toString();

            FailedJob::create([
                'uuid' => $uuid,
                'connection' => $connection,
                'queue' => $queue,
                'payload' => $payload,
                'exception' => (string) $exception,
            ]);

            return $uuid;
        } catch (Throwable $e) {
            // If logging the failed job fails, we log this critical error
            // to the main application logger and return null.
            app('log')->critical(
                'Failed to log a failed job.',
                ['exception' => $e, 'original_payload' => $payload],
            );

            return null;
        }
    }

    /**
     * Get a list of all of the failed jobs.
     *
     * @return Collection
     */
    public function all(): Collection
    {
        // Use get() to execute the query builder and retrieve a collection.
        // This is the conventional method that ensures the orderBy clause is applied
        // and the return type matches the Collection specified in the interface.
        return FailedJob::orderBy('id', 'desc')->get();
    }

    /**
     * Find a failed job by its UUID.
     *
     * @param string $uuid
     * @return object|null A failed job object, or null if not found.
     */
    public function find(string $uuid): ?object
    {
        return FailedJob::where('uuid', '=', $uuid)->first();
    }

    /**
     * Delete a single failed job from storage.
     *
     * @param string $uuid
     * @return bool True if a job was deleted, false otherwise.
     */
    public function forget(string $uuid): bool
    {
        return FailedJob::where('uuid', '=', $uuid)->delete() > 0;
    }

    /**
     * Flush failed jobs from storage.
     *
     * @param string|null $queue Optionally flush only jobs from a specific queue.
     * @return int The number of jobs deleted.
     */
    public function flush(?string $queue = null): int
    {
        return FailedJob::query()
            ->when($queue, fn ($query) => $query->where('queue', '=', $queue))
            ->delete();
    }

    /**
     * Count the failed jobs.
     *
     * @param string|null $queue Optionally count only jobs from a specific queue.
     * @return int
     */
    public function count(?string $queue = null): int
    {
        return FailedJob::query()->when($queue, fn ($q) => $q->where('queue', '=', $queue))->count();
    }
}
