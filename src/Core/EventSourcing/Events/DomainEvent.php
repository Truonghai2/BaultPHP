<?php

namespace Core\EventSourcing\Events;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

/**
 * Base Domain Event
 *
 * All domain events must extend this class.
 * Events are immutable and contain all data needed to reconstruct state.
 */
abstract class DomainEvent
{
    /**
     * Unique event ID
     */
    public readonly string $eventId;

    /**
     * When the event occurred
     */
    public readonly DateTimeImmutable $occurredAt;

    /**
     * Event version (for schema evolution)
     */
    public readonly int $eventVersion;

    /**
     * Metadata (user ID, IP, correlation ID, etc.)
     */
    public readonly array $metadata;

    public function __construct(
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
        int $eventVersion = 1,
        array $metadata = [],
    ) {
        $this->eventId = $eventId ?? Uuid::uuid4()->toString();
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
        $this->eventVersion = $eventVersion;
        $this->metadata = $metadata;
    }

    /**
     * Get event name (for serialization)
     */
    public function getEventName(): string
    {
        return static::class;
    }

    /**
     * Serialize event to array
     */
    public function toArray(): array
    {
        $data = get_object_vars($this);
        $data['occurredAt'] = $this->occurredAt->format('Y-m-d H:i:s.u');

        return $data;
    }

    /**
     * Deserialize event from array
     */
    public static function fromArray(array $data): static
    {
        // This should be implemented in child classes
        // for proper reconstruction
        throw new \RuntimeException('fromArray must be implemented in child class');
    }
}
