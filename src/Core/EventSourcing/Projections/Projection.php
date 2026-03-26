<?php

namespace Core\EventSourcing\Projections;

use Core\EventSourcing\Event;

/**
 * Base class for Projections (Read Models).
 * 
 * Projections build read models from events.
 */
abstract class Projection
{
    /**
     * Handle an event and update the read model.
     */
    abstract public function handle(Event $event): void;

    /**
     * Get event types this projection handles.
     *
     * @return array<string>
     */
    abstract public function subscribedEvents(): array;

    /**
     * Reset the projection (delete all data).
     */
    abstract public function reset(): void;
}
