<?php 

namespace Core\CQRS;

use PDO;

class EventStore
{
    protected array $events = [];
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function record(object $event, string $aggregateType, string $aggregateId): void
    {
        $this->events[] = $event;

        $stmt = $this->pdo->prepare(
            "INSERT INTO event_store (aggregate_type, aggregate_id, event_type, event_payload)
             VALUES (:aggregate_type, :aggregate_id, :event_type, :event_payload)"
        );

        $stmt->execute([
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => get_class($event),
            'event_payload'  => json_encode($event),
        ]);
    }

    public function getEvents(): array
    {
        return $this->events;
    }
}
