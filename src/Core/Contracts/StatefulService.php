<?php

namespace Core\Contracts;

/**
 * Interface StatefulService
 *
 * Marks a service as containing state that is specific to a single request.
 * Services implementing this interface will have their `resetState` method
 * called automatically after each request in a long-running environment like Swoole.
 */
interface StatefulService
{
    /**
     * Resets the internal state of the service to its initial condition.
     */
    public function resetState(): void;
}
