<?php

namespace Core\Queue;

use Core\Application;
use Closure;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Represents a batch of jobs that should be processed together.
 * 
 * Features:
 * - Track batch progress
 * - Callback when all jobs complete
 * - Callback on first job failure
 * - Callback when batch finishes (success or failure)
 * - Cancel remaining jobs on failure
 */
class Batch
{
    protected string $id;
    protected string $name;
    protected int $totalJobs = 0;
    protected int $pendingJobs = 0;
    protected int $failedJobs = 0;
    protected int $processedJobs = 0;
    protected array $failedJobIds = [];
    protected ?Closure $thenCallback = null;
    protected ?Closure $catchCallback = null;
    protected ?Closure $finallyCallback = null;
    protected bool $cancelled = false;
    protected bool $allowsFailures = false;
    protected ?int $createdAt = null;
    protected ?int $finishedAt = null;
    protected array $options = [];

    public function __construct(
        protected Application $app,
        string $id = null,
        string $name = '',
        int $totalJobs = 0,
        int $pendingJobs = 0,
        int $failedJobs = 0,
        array $failedJobIds = [],
        array $options = []
    ) {
        $this->id = $id ?? Uuid::uuid4()->toString();
        $this->name = $name ?: 'Batch #' . $this->id;
        $this->totalJobs = $totalJobs;
        $this->pendingJobs = $pendingJobs;
        $this->failedJobs = $failedJobs;
        $this->failedJobIds = $failedJobIds;
        $this->options = $options;
        $this->createdAt = time();
    }

    /**
     * Get the batch ID.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get the batch name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get the total number of jobs in the batch.
     */
    public function totalJobs(): int
    {
        return $this->totalJobs;
    }

    /**
     * Get the number of pending jobs.
     */
    public function pendingJobs(): int
    {
        return $this->pendingJobs;
    }

    /**
     * Get the number of failed jobs.
     */
    public function failedJobs(): int
    {
        return $this->failedJobs;
    }

    /**
     * Get the number of processed jobs.
     */
    public function processedJobs(): int
    {
        return $this->processedJobs;
    }

    /**
     * Get the completion percentage.
     */
    public function progress(): float
    {
        return $this->totalJobs > 0
            ? ($this->processedJobs / $this->totalJobs) * 100
            : 0;
    }

    /**
     * Determine if the batch is finished (all jobs processed or cancelled).
     */
    public function finished(): bool
    {
        return $this->pendingJobs === 0 || $this->cancelled;
    }

    /**
     * Determine if the batch is cancelled.
     */
    public function cancelled(): bool
    {
        return $this->cancelled;
    }

    /**
     * Determine if all jobs completed successfully.
     */
    public function hasFailures(): bool
    {
        return $this->failedJobs > 0;
    }

    /**
     * Determine if the batch allows failures.
     */
    public function allowsFailures(): bool
    {
        return $this->allowsFailures;
    }

    /**
     * Mark the batch as allowing failures.
     */
    public function allowFailures(bool $allow = true): self
    {
        $this->allowsFailures = $allow;
        return $this;
    }

    /**
     * Set the callback to execute when all jobs complete successfully.
     */
    public function then(Closure $callback): self
    {
        $this->thenCallback = $callback;
        return $this;
    }

    /**
     * Set the callback to execute on first job failure.
     */
    public function catch(Closure $callback): self
    {
        $this->catchCallback = $callback;
        return $this;
    }

    /**
     * Set the callback to execute when batch finishes (regardless of success/failure).
     */
    public function finally(Closure $callback): self
    {
        $this->finallyCallback = $callback;
        return $this;
    }

    /**
     * Increment the number of jobs in the batch.
     */
    public function incrementTotalJobs(int $count = 1): void
    {
        $this->totalJobs += $count;
        $this->pendingJobs += $count;
    }

    /**
     * Record that a job has been processed.
     */
    public function recordSuccessfulJob(string $jobId): void
    {
        $this->processedJobs++;
        $this->pendingJobs = max(0, $this->pendingJobs - 1);

        $this->persist();

        if ($this->finished() && !$this->hasFailures()) {
            $this->invokeCallback($this->thenCallback);
            $this->invokeCallback($this->finallyCallback);
        } elseif ($this->finished()) {
            $this->invokeCallback($this->finallyCallback);
        }
    }

    /**
     * Record that a job has failed.
     */
    public function recordFailedJob(string $jobId, Throwable $e): void
    {
        $this->processedJobs++;
        $this->failedJobs++;
        $this->pendingJobs = max(0, $this->pendingJobs - 1);
        $this->failedJobIds[] = $jobId;

        $this->persist();

        // First failure
        if ($this->failedJobs === 1 && !$this->allowsFailures) {
            $this->cancel();
            $this->invokeCallback($this->catchCallback, [$this, $e]);
        }

        if ($this->finished()) {
            $this->invokeCallback($this->finallyCallback);
        }
    }

    /**
     * Cancel the batch.
     */
    public function cancel(): void
    {
        $this->cancelled = true;
        $this->finishedAt = time();
        $this->persist();
    }

    /**
     * Invoke a callback if it exists.
     */
    protected function invokeCallback(?Closure $callback, array $args = []): void
    {
        if ($callback) {
            try {
                $callback($this, ...$args);
            } catch (Throwable $e) {
                $this->app->make('log')->error('Batch callback failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'batch_id' => $this->id,
                ]);
            }
        }
    }

    /**
     * Persist the batch state to storage.
     */
    protected function persist(): void
    {
        $repository = $this->app->make(BatchRepository::class);
        $repository->store($this);
    }

    /**
     * Convert the batch to an array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'total_jobs' => $this->totalJobs,
            'pending_jobs' => $this->pendingJobs,
            'failed_jobs' => $this->failedJobs,
            'processed_jobs' => $this->processedJobs,
            'progress' => $this->progress(),
            'failed_job_ids' => $this->failedJobIds,
            'cancelled' => $this->cancelled,
            'allows_failures' => $this->allowsFailures,
            'finished' => $this->finished(),
            'has_failures' => $this->hasFailures(),
            'created_at' => $this->createdAt,
            'finished_at' => $this->finishedAt,
            'options' => $this->options,
        ];
    }

    /**
     * Restore a batch from an array.
     */
    public static function fromArray(Application $app, array $data): self
    {
        $batch = new self(
            $app,
            $data['id'],
            $data['name'],
            $data['total_jobs'],
            $data['pending_jobs'],
            $data['failed_jobs'],
            $data['failed_job_ids'] ?? [],
            $data['options'] ?? []
        );

        $batch->processedJobs = $data['processed_jobs'];
        $batch->cancelled = $data['cancelled'];
        $batch->allowsFailures = $data['allows_failures'] ?? false;
        $batch->createdAt = $data['created_at'];
        $batch->finishedAt = $data['finished_at'] ?? null;

        return $batch;
    }
}
