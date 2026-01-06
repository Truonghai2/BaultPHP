<?php

namespace Core\CQRS\Contracts;

/**
 * Query Interface
 * 
 * All queries must implement this interface.
 * Queries represent read operations (no side effects).
 */
interface QueryInterface
{
    /**
     * Get query name (for logging/tracing)
     */
    public function getQueryName(): string;
}

