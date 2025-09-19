<?php

namespace Core\Queue;

use Core\Contracts\Queue\Job as JobContract;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * An abstract base class for jobs to reduce boilerplate code.
 * It provides default implementations for queue-related mechanics, allowing
 * concrete job classes to focus solely on their business logic.
 */
abstract class BaseJob implements JobContract, \Serializable
{
    /**
     * The number of times the job may be attempted.
     * Can be overridden by child classes.
     * @var int
     */
    public int $tries = 1;

    /**
     * The number of times the job has been attempted.
     * This value is managed by the queue worker.
     * @var int
     */
    public int $attempts = 0;

    /**
     * The job's unique identifier.
     * This value is managed by the queue system.
     * @var string|null
     */
    public ?string $jobId = null;

    /**
     * The raw body of the job.
     * This value is managed by the queue system.
     * @var string|null
     */
    public ?string $rawBody = null;

    /**
     * Execute the job.
     * This method must be implemented by the child class.
     */
    abstract public function handle(): void;

    /**
     * Handle a job failure.
     * This can be overridden by the child class for custom failure logic.
     *
     * @param \Throwable|null $e
     * @param LoggerInterface|null $logger Injected by the container.
     */
    public function fail(Throwable $e = null, LoggerInterface $logger = null): void
    {
        if ($e && $logger) {
            $logger->error(
                static::class . ' failed: ' . $e->getMessage(),
                ['exception' => $e],
            );
        }
    }

    // --- Boilerplate methods to satisfy the Job contract ---
    public function delete(): void
    {
    }
    public function release($delay = 0): void
    {
    }
    public function markAsFailed(): void
    {
    }
    public function hasFailed(): bool
    {
        return false;
    }
    public function getJobId(): string
    {
        return $this->jobId ?? '';
    }
    public function getRawBody(): string
    {
        return $this->rawBody ?? $this->serialize();
    }
    public function attempts(): int
    {
        return $this->attempts;
    }
    public function maxTries(): ?int
    {
        return $this->tries ?? null;
    }

    // --- Serialization ---
    public function __serialize(): array
    {
        return [];
    }
    public function __unserialize(array $data): void
    {
    }
    public function serialize(): string
    {
        return serialize($this->__serialize());
    }
    public function unserialize($data): void
    {
        $this->__unserialize(unserialize($data));
    }
}
