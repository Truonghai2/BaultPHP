<?php

namespace Core\Console\Contracts\Console;

/**
 * Interface for services that hold state within a single request-response cycle.
 * In long-running applications (like RoadRunner), these services must be reset
 * after each request to prevent state leakage.
 */
interface StatefulService
{
    /**
     * Resets the service's state to its initial condition.
     */
    public function resetState(): void;
}
