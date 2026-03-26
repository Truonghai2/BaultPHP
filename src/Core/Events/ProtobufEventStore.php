<?php

namespace Core\Events;

use Core\Streaming\ProtobufEventSerializer;
use Psr\Log\LoggerInterface;

/**
 * Event Store with Protobuf Serialization.
 * 
 * Stores events in binary Protobuf format for:
 * - 3-10x storage savings
 * - Faster serialization/deserialization
 * - Type safety
 */
class ProtobufEventStore extends EventStore
{
    private ProtobufEventSerializer $serializer;

    public function __construct(
        \PDO $db,
        LoggerInterface $logger,
        ProtobufEventSerializer $serializer
    ) {
        parent::__construct($db, $logger);
        $this->serializer = $serializer;
    }

    /**
     * Append event to store.
     */
    public function append(Event $event): void
    {
        $this->logger->debug("Appending event to store (Protobuf)", [
            'event' => get_class($event),
            'aggregate_id' => method_exists($event, 'getAggregateId') ? $event->getAggregateId() : null,
        ]);

        // Serialize to Protobuf binary
        $serialized = $this->serializer->serialize($event);

        $sql = "
            INSERT INTO event_store (
                event_id,
                event_type,
                aggregate_id,
                aggregate_type,
                sequence,
                payload,
                payload_format,
                occurred_at,
                correlation_id,
                created_at
            ) VALUES (
                :event_id,
                :event_type,
                :aggregate_id,
                :aggregate_type,
                :sequence,
                :payload,
                :payload_format,
                :occurred_at,
                :correlation_id,
                NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'event_id' => $event->getEventId(),
            'event_type' => get_class($event),
            'aggregate_id' => method_exists($event, 'getAggregateId') ? $event->getAggregateId() : null,
            'aggregate_type' => method_exists($event, 'getAggregateType') ? $event->getAggregateType() : null,
            'sequence' => method_exists($event, 'getSequence') ? $event->getSequence() : null,
            'payload' => $serialized,
            'payload_format' => 'protobuf',
            'occurred_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
            'correlation_id' => $event->getCorrelationId(),
        ]);

        $this->logger->info("Event appended to store (Protobuf)", [
            'event' => get_class($event),
            'size' => strlen($serialized),
            'format' => 'protobuf',
        ]);
    }

    /**
     * Load events for aggregate.
     */
    public function loadEvents(string $aggregateId): array
    {
        $sql = "
            SELECT * FROM event_store
            WHERE aggregate_id = :aggregate_id
            ORDER BY sequence ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['aggregate_id' => $aggregateId]);

        $events = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $events[] = $this->deserializeEvent($row);
        }

        return $events;
    }

    /**
     * Load events from position.
     */
    public function loadEventsFrom(int $position, int $limit = 100): array
    {
        $sql = "
            SELECT * FROM event_store
            WHERE id > :position
            ORDER BY id ASC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('position', $position, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $events = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $events[] = $this->deserializeEvent($row);
        }

        return $events;
    }

    /**
     * Deserialize event from row.
     */
    protected function deserializeEvent(array $row): Event
    {
        $format = $row['payload_format'] ?? 'json';

        if ($format === 'protobuf') {
            return $this->serializer->deserialize($row['payload']);
        }

        // Fallback to JSON
        return parent::deserializeEvent($row);
    }

    /**
     * Get storage statistics.
     */
    public function getStorageStats(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_events,
                SUM(CASE WHEN payload_format = 'protobuf' THEN 1 ELSE 0 END) as protobuf_events,
                SUM(CASE WHEN payload_format = 'json' THEN 1 ELSE 0 END) as json_events,
                AVG(LENGTH(payload)) as avg_size,
                SUM(LENGTH(payload)) as total_size
            FROM event_store
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
