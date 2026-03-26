<?php

namespace Core\EventSourcing\EventStore;

use Core\EventSourcing\Event;

/**
 * Event Store for persisting domain events.
 * 
 * Provides append-only storage of events for Event Sourcing.
 */
interface EventStore
{
    /**
     * Append events to the store.
     *
     * @param string $aggregateId
     * @param string $aggregateType
     * @param array<Event> $events
     * @param int $expectedVersion Expected version for optimistic concurrency
     * @return void
     * @throws ConcurrencyException if version mismatch
     */
    public function append(
        string $aggregateId,
        string $aggregateType,
        array $events,
        int $expectedVersion
    ): void;

    /**
     * Get all events for an aggregate.
     *
     * @param string $aggregateId
     * @param string $aggregateType
     * @param int $fromVersion Start from this version (inclusive)
     * @return array<Event>
     */
    public function getEventsFor(
        string $aggregateId,
        string $aggregateType,
        int $fromVersion = 0
    ): array;

    /**
     * Get all events of a specific type.
     *
     * @param string $eventType
     * @param int $fromPosition Start from this position
     * @param int $limit
     * @return array<Event>
     */
    public function getEventsByType(
        string $eventType,
        int $fromPosition = 0,
        int $limit = 100
    ): array;

    /**
     * Get all events in the store.
     *
     * @param int $fromPosition
     * @param int $limit
     * @return array<Event>
     */
    public function getAllEvents(
        int $fromPosition = 0,
        int $limit = 100
    ): array;
}
