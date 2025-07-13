<?php

namespace Core\Contracts\Queue;

/**
 * Interface for a queueable job.
 */
interface Job
{
    public function handle(): void;
}