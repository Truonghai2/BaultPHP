<?php

namespace Core\Queue;

use Carbon\Carbon;
use Core\Contracts\Queue\FailedJobProviderInterface;
use Core\Queue\FailedJob;
use Core\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Class DatabaseFailedJobProvider
 * An implementation of FailedJobProviderInterface that uses a database table to store failed jobs,
 * leveraging the framework's core ORM (FailedJob model).
 */
class DatabaseFailedJobProvider implements FailedJobProviderInterface
{
    /**
     * Create a new database failed job provider.
     * This implementation uses the Core\ORM\Model (FailedJob) and does not require
     * any dependencies in the constructor, making it easy for the DI container to resolve.
     */
    public function __construct()
    {
        // Constructor is intentionally empty.
    }

    /**
     * {@inheritdoc}
     */
    public function log(string $connection, string $queue, string $payload, Throwable $exception): ?string
    {
        try {
            $uuid = Str::orderedUuid()->toString();

            FailedJob::create([
                'uuid' => $uuid,
                'connection' => $connection,
                'queue' => $queue,
                'payload' => $payload,
                'exception' => (string) $exception,
                'failed_at' => Carbon::now(),
            ]);

            return $uuid;
        } catch (Throwable $e) {
            if (function_exists('app') && app()->bound('log')) {
                app('log')->critical(
                    'Could not log a failed job to the database.',
                    ['exception' => $e, 'original_payload' => $payload]
                );
            }

            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function all(): Collection
    {
        // The ORM returns an Illuminate\Support\Collection.
        // We convert it to the framework's Core\Support\Collection to match the interface.
        $illuminateCollection = FailedJob::orderBy('id', 'desc')->get();

        return new Collection($illuminateCollection->all());
    }

    /**
     * {@inheritdoc}
     */
    public function find(string $uuid): ?object
    {
        return FailedJob::where('uuid', $uuid)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function forget(string $uuid): bool
    {
        // The delete method on the query builder returns the number of affected rows.
        return FailedJob::where('uuid', $uuid)->delete() > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function flush(?string $queue = null): int
    {
        $query = FailedJob::query();

        if ($queue) {
            $query->where('queue', $queue);
        }

        return $query->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function count(?string $queue = null): int
    {
        $query = FailedJob::query();

        if ($queue) {
            $query->where('queue', $queue);
        }

        return $query->count();
    }
}
