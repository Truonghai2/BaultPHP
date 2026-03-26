<?php

namespace Core\Events;

/**
 * Base Projection Interface.
 * 
 * Projections transform domain events into read models.
 */
interface Projection
{
    /**
     * Get list of events this projection handles.
     * 
     * @return array<string> Event class names
     */
    public function handles(): array;

    /**
     * Project event to read model.
     * 
     * @param Event $event Domain event
     */
    public function project(Event $event): void;

    /**
     * Reset projection (rebuild from scratch).
     */
    public function reset(): void;
}
