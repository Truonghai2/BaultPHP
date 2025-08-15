<?php

namespace Core\Contracts\Queue;

use Throwable;

interface Job
{
    /**
     * Execute the job.
     */
    public function handle(): void;

    /**
     * Delete the job from the queue.
     */
    public function delete(): void;

    /**
     * Release the job back onto the queue.
     *
     * @param int $delay
     */
    public function release($delay = 0): void;

    /**
     * Get the job identifier.
     */
    public function getJobId(): string;

    /**
     * Get the raw body string for the job.
     */
    public function getRawBody(): string;

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts(): int;

    /**
     * Mark the job as "failed".
     *
     * @return void
     */
    public function markAsFailed(): void;

    /**
     * Determine if the job has been marked as a failure.
     *
     * @return bool
     */
    public function hasFailed(): bool;

    /**
     * Delete the job, call the "failed" method if it exists, and log the failure.
     *
     * @param  \Throwable|null  $e
     * @return void
     */
    public function fail(Throwable $e = null): void;

    /**
     * Get the number of times to attempt a job before it is considered failed.
     *
     * @return int|null
     */
    public function maxTries(): ?int;
}
