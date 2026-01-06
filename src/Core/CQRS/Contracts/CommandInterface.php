<?php

namespace Core\CQRS\Contracts;

/**
 * Command Interface
 *
 * All commands must implement this interface.
 * Commands represent write operations (mutations).
 */
interface CommandInterface
{
    /**
     * Get command name (for logging/tracing)
     */
    public function getCommandName(): string;
}
