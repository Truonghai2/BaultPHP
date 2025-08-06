<?php

namespace Core\Contracts\Queue;

use Serializable;

/**
 * Interface Job
 * Represents a unit of work that can be pushed onto a queue and processed asynchronously.
 * Jobs must be serializable to be passed to Swoole task workers.
 */
interface Job extends Serializable
{
    public function handle(): void;
}
