<?php

namespace Core\Contracts\Queue;

use DateInterval;
use DateTimeInterface;

interface Queue
{
    /**
     * Push a new job onto the queue.
     *
     * @param  Job  $job
     * @param  string|null  $queue
     * @return void
     */
    public function push(Job $job, ?string $queue = null): void;

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  DateTimeInterface|DateInterval|int  $delay
     * @param  Job  $job
     * @param  string|null  $queue
     * @return void
     */
    public function later($delay, Job $job, ?string $queue = null): void;

    /**
     * Pop the next job off of the queue.
     */
    public function pop(?string $queue = null): ?Job;
}
