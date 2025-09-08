<?php

namespace Core\Queue\Jobs;

use Core\Application;
use Core\Contracts\Queue\FailedJobProviderInterface;
use Core\Contracts\Queue\Job as JobContract;
use Redis;
use Throwable;

/**
 * Class RedisJob
 *
 * Represents a job that has been popped from a Redis queue.
 * This class acts as a wrapper around the original job payload, providing
 * methods to interact with Redis for job lifecycle management (delete, release, fail).
 */
class RedisJob implements JobContract
{
    /**
     * The unserialized job instance.
     * @var object
     */
    protected object $instance;

    /**
     * The JSON-decoded payload of the job.
     * @var array
     */
    protected array $decoded;

    /**
     * Indicates if the job has been deleted.
     * @var bool
     */
    protected bool $deleted = false;

    /**
     * Indicates if the job has been released back to the queue.
     * @var bool
     */
    protected bool $released = false;

    /**
     * The failed job provider instance.
     * @var FailedJobProviderInterface
     */
    protected FailedJobProviderInterface $failer;

    /**
     * Create a new job instance.
     *
     * @param Application $app The application container.
     * @param Redis $redis The Redis connection instance.
     * @param string $job The raw payload of the job.
     * @param string $connectionName The name of the queue connection.
     * @param string $queue The name of the queue the job belongs to.
     */
    public function __construct(
        protected Application $app,
        protected Redis $redis,
        protected string $job,
        protected string $connectionName,
        protected string $queue,
    ) {
        $this->decoded = json_decode($this->job, true);
        $this->instance = unserialize($this->decoded['data']['command']);
        $this->failer = $this->app->make(FailedJobProviderInterface::class);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Use the container to call the handle method. This allows for
        // method dependency injection on the job's handle method.
        $this->app->call([$this->instance, 'handle']);
    }

    /**
     * Delete the job from the queue.
     * For Redis, this means removing it from the reserved queue.
     */
    public function delete(): void
    {
        // In a real-world scenario, you'd remove this from a "reserved" list/zset.
        // For this example, we'll assume it's handled or not needed if pop was destructive.
        $this->deleted = true;
    }

    /**
     * Release the job back onto the queue.
     *
     * @param int $delay
     */
    public function release($delay = 0): void
    {
        $this->released = true;

        // The worker is responsible for incrementing attempts.
        // The release method should just put the job back on the queue.
        // We simply re-encode the existing payload.

        // Re-serialize the job with the updated attempt count.
        $payload = json_encode($this->decoded);

        // Push it to the 'delayed' sorted set to be picked up later.
        $this->redis->zAdd(
            $this->queue . ':delayed',
            ['NX'], // Add only if it does not exist
            time() + $delay,
            $payload,
        );
    }

    /**
     * Delete the job, call the "failed" method if it exists, and log the failure.
     *
     * @param Throwable|null $e
     */
    public function fail(Throwable $e = null): void
    {
        // 1. Log the job to the failed jobs storage (e.g., 'failed_jobs' table).
        $this->failer->log($this->connectionName, $this->getQueue(), $this->getRawBody(), $e);

        // 2. If the user-defined job class has a `failed(Throwable $e)` method, call it.
        //    This allows for custom logic upon failure, like sending a notification.
        if (method_exists($this->instance, 'failed')) {
            $this->instance->failed($e);
        }

        // 3. Ensure the job is marked as deleted so it's not processed again.
        $this->delete();
    }

    /**
     * Get the number of times the job has been attempted.
     */
    public function attempts(): int
    {
        return ($this->decoded['attempts'] ?? 0) + 1;
    }

    /**
     * Get the number of times to attempt a job before it is considered failed.
     * This reads a public property from the user's job class.
     */
    public function maxTries(): ?int
    {
        return $this->instance->tries ?? null;
    }

    /**
     * Get the job identifier.
     */
    public function getJobId(): string
    {
        return $this->decoded['id'];
    }

    /**
     * Get the display name of the queued job.
     *
     * @return string
     */
    public function resolveName(): string
    {
        // If the underlying job has a specific display name, use it.
        if (method_exists($this->instance, 'displayName')) {
            return $this->instance->displayName();
        }
        return get_class($this->instance);
    }

    /**
     * Get the raw body string for the job.
     */
    public function getRawBody(): string
    {
        return $this->job;
    }

    /**
     * Get the name of the queue the job belongs to.
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    // Các phương thức còn lại trong interface (markAsFailed, hasFailed) có thể được
    // implement đơn giản hoặc được quản lý bởi các cờ (flags) nội bộ nếu cần.
    public function markAsFailed(): void
    {
    }
    public function hasFailed(): bool
    {
        return false;
    }
    public function isDeleted(): bool
    {
        return $this->deleted;
    }
    public function isReleased(): bool
    {
        return $this->released;
    }
}
