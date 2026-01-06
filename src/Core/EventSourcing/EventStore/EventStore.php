<?php

namespace Core\EventSourcing\EventStore;

use Core\EventSourcing\AggregateRoot;
use Core\EventSourcing\Events\DomainEvent;
use PDO;

/**
 * Event Store
 * 
 * Persistent storage for domain events.
 * Uses PostgreSQL for ACID guarantees and JSON support.
 */
class EventStore
{
    public function __construct(
        private PDO $connection
    ) {
    }

    /**
     * Save events for an aggregate
     */
    public function saveEvents(
        string $aggregateType,
        string $aggregateId,
        array $events,
        int $expectedVersion
    ): void {
        if (empty($events)) {
            return;
        }

        $this->connection->beginTransaction();

        try {
            // Lock aggregate for optimistic concurrency control
            $stmt = $this->connection->prepare(
                "SELECT version FROM aggregates 
                 WHERE aggregate_type = ? AND aggregate_id = ? 
                 FOR UPDATE"
            );
            $stmt->execute([$aggregateType, $aggregateId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $currentVersion = $row ? (int)$row['version'] : 0;

            // Check for concurrency conflicts
            if ($currentVersion !== $expectedVersion) {
                throw new \RuntimeException(
                    "Concurrency conflict: Expected version {$expectedVersion}, " .
                    "but aggregate is at version {$currentVersion}"
                );
            }

            // Insert events
            $stmt = $this->connection->prepare(
                "INSERT INTO events (
                    event_id,
                    aggregate_type,
                    aggregate_id,
                    event_type,
                    event_data,
                    event_version,
                    aggregate_version,
                    metadata,
                    occurred_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $version = $currentVersion;
            foreach ($events as $event) {
                $version++;
                
                $stmt->execute([
                    $event->eventId,
                    $aggregateType,
                    $aggregateId,
                    $event->getEventName(),
                    json_encode($event->toArray()),
                    $event->eventVersion,
                    $version,
                    json_encode($event->metadata),
                    $event->occurredAt->format('Y-m-d H:i:s.u'),
                ]);
            }

            // Update aggregate version
            if ($currentVersion === 0) {
                $stmt = $this->connection->prepare(
                    "INSERT INTO aggregates (aggregate_type, aggregate_id, version, updated_at)
                     VALUES (?, ?, ?, NOW())"
                );
                $stmt->execute([$aggregateType, $aggregateId, $version]);
            } else {
                $stmt = $this->connection->prepare(
                    "UPDATE aggregates 
                     SET version = ?, updated_at = NOW()
                     WHERE aggregate_type = ? AND aggregate_id = ?"
                );
                $stmt->execute([$version, $aggregateType, $aggregateId]);
            }

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Load events for an aggregate
     */
    public function getEvents(string $aggregateType, string $aggregateId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT event_type, event_data, occurred_at
             FROM events
             WHERE aggregate_type = ? AND aggregate_id = ?
             ORDER BY aggregate_version ASC"
        );
        
        $stmt->execute([$aggregateType, $aggregateId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        foreach ($rows as $row) {
            $eventClass = $row['event_type'];
            $eventData = json_decode($row['event_data'], true);
            
            if (class_exists($eventClass)) {
                $events[] = $eventClass::fromArray($eventData);
            }
        }

        return $events;
    }

    /**
     * Get all events (for projections/replay)
     */
    public function getAllEvents(?string $fromEventId = null, int $limit = 1000): array
    {
        $sql = "SELECT event_id, event_type, event_data, aggregate_type, aggregate_id, occurred_at
                FROM events ";
        
        $params = [];
        if ($fromEventId) {
            $sql .= "WHERE event_id > ? ";
            $params[] = $fromEventId;
        }
        
        $sql .= "ORDER BY id ASC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get events by type (for specific projections)
     */
    public function getEventsByType(string $eventType, int $limit = 1000): array
    {
        $stmt = $this->connection->prepare(
            "SELECT event_id, event_data, aggregate_type, aggregate_id, occurred_at
             FROM events
             WHERE event_type = ?
             ORDER BY id ASC
             LIMIT ?"
        );
        
        $stmt->execute([$eventType, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

