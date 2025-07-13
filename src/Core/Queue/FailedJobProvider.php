<?php

namespace Core\Queue;

use Throwable;
use Ramsey\Uuid\Uuid;

class FailedJobProvider
{
    /**
     * Log a failed job into storage.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $payload
     * @param  \Throwable  $exception
     * @return string|null
     */
    public function log(string $connection, string $queue, string $payload, Throwable $exception): ?string
    {
        $uuid = Uuid::uuid4()->toString();
        FailedJob::create([
            'uuid' => $uuid,
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $payload,
            'exception' => (string) $exception,
        ]);
        return $uuid;
    }
}