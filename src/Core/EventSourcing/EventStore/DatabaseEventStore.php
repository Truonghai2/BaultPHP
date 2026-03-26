<?php

namespace Core\EventSourcing\EventStore;

use Core\Database\Connection;
use Core\EventSourcing\Event;

/**
 * Database-backed Event Store.
 * 
 * Stores events in a relational database (PostgreSQL/MySQL).
 */
class DatabaseEventStore implements EventStore
{
    public function __construct(
        private Connection $db
    ) {}

    /**
     * Append events to the store.
     */
    public function append(
        string $aggregateId,
        string $aggregateType,
        array $events,
        int $expectedVersion
    ): void {
        // Start transaction
        $this->db->beginTransaction();

        try {
            // Check current version (optimistic concurrency)
            $currentVersion = $this->getCurrentVersion($aggregateId, $aggregateType);

            if ($currentVersion !== $expectedVersion) {
                throw new \RuntimeException(
                    "Concurrency conflict: expected version $expectedVersion, got $currentVersion"
                );
            }

            // Insert events
            $version = $expectedVersion;
            foreach ($events as $event) {
                $version++;

                $event->setAggregateId($aggregateId);
                $event->setAggregateType($aggregateType);
                $event->setAggregateVersion($version);

                $this->db->table('event_store')->insert([
                    'event_id' => $event->eventId(),
                    'aggregate_id' => $aggregateId,
                    'aggregate_type' => $aggregateType,
                    'aggregate_version' => $version,
                    'event_type' => $event->eventName(),
                    'event_data' => json_encode($event->toArray()),
                    'metadata' => json_encode($event->metadata()),
                    'occurred_at' => $event->occurredAt(),
                    'created_at' => time(),
                ]);
            }

            $this->db->commit();

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get all events for an aggregate.
     */
    public function getEventsFor(
        string $aggregateId,
        string $aggregateType,
        int $fromVersion = 0
    ): array {
        $rows = $this->db->table('event_store')
            ->where('aggregate_id', $aggregateId)
            ->where('aggregate_type', $aggregateType)
            ->where('aggregate_version', '>=', $fromVersion)
            ->orderBy('aggregate_version', 'ASC')
            ->get();

        return array_map(
            fn($row) => $this->hydrateEvent($row),
            $rows
        );
    }

    /**
     * Get events by type.
     */
    public function getEventsByType(
        string $eventType,
        int $fromPosition = 0,
        int $limit = 100
    ): array {
        $rows = $this->db->table('event_store')
            ->where('event_type', $eventType)
            ->where('id', '>', $fromPosition)
            ->orderBy('id', 'ASC')
            ->limit($limit)
            ->get();

        return array_map(
            fn($row) => $this->hydrateEvent($row),
            $rows
        );
    }

    /**
     * Get all events.
     */
    public function getAllEvents(
        int $fromPosition = 0,
        int $limit = 100
    ): array {
        $rows = $this->db->table('event_store')
            ->where('id', '>', $fromPosition)
            ->orderBy('id', 'ASC')
            ->limit($limit)
            ->get();

        return array_map(
            fn($row) => $this->hydrateEvent($row),
            $rows
        );
    }

    /**
     * Get current version of aggregate.
     */
    private function getCurrentVersion(string $aggregateId, string $aggregateType): int
    {
        $row = $this->db->table('event_store')
            ->where('aggregate_id', $aggregateId)
            ->where('aggregate_type', $aggregateType)
            ->orderBy('aggregate_version', 'DESC')
            ->first();

        return $row ? $row->aggregate_version : 0;
    }

    /**
     * Hydrate event from database row.
     */
    private function hydrateEvent(object $row): Event
    {
        $eventClass = $this->getEventClass($row->event_type);
        $eventData = json_decode($row->event_data, true);

        $event = $eventClass::fromArray($eventData);
        $event->setEventId($row->event_id);
        $event->setAggregateId($row->aggregate_id);
        $event->setAggregateType($row->aggregate_type);
        $event->setAggregateVersion($row->aggregate_version);
        $event->setOccurredAt($row->occurred_at);
        $event->setMetadata(json_decode($row->metadata, true) ?? []);

        return $event;
    }

    /**
     * Get event class from event type.
     */
    private function getEventClass(string $eventType): string
    {
        // Map event type to class
        // In production, use a registry
        $mapping = [
            'todo.created' => \Modules\Todo\Domain\Events\TodoCreated::class,
            'todo.completed' => \Modules\Todo\Domain\Events\TodoCompleted::class,
            'todo.uncompleted' => \Modules\Todo\Domain\Events\TodoUncompleted::class,
        ];

        return $mapping[$eventType] ?? throw new \RuntimeException("Unknown event type: $eventType");
    }
}
