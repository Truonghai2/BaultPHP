<?php

namespace Core\EventSourcing;

/**
 * Base Domain Event for Event Sourcing.
 * 
 * All domain events should extend this class to be persistable.
 */
abstract class Event
{
    protected string $eventId;
    protected string $aggregateId;
    protected string $aggregateType;
    protected int $aggregateVersion;
    protected int $occurredAt;
    protected array $metadata = [];

    public function __construct()
    {
        $this->eventId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->occurredAt = time();
    }

    /**
     * Get event name (for event store).
     */
    abstract public function eventName(): string;

    /**
     * Convert event to array for persistence.
     */
    abstract public function toArray(): array;

    /**
     * Reconstitute event from array.
     */
    abstract public static function fromArray(array $data): self;

    // Getters
    public function eventId(): string { return $this->eventId; }
    public function aggregateId(): string { return $this->aggregateId; }
    public function aggregateType(): string { return $this->aggregateType; }
    public function aggregateVersion(): int { return $this->aggregateVersion; }
    public function occurredAt(): int { return $this->occurredAt; }
    public function metadata(): array { return $this->metadata; }

    // Setters (for hydration)
    public function setEventId(string $id): void { $this->eventId = $id; }
    public function setAggregateId(string $id): void { $this->aggregateId = $id; }
    public function setAggregateType(string $type): void { $this->aggregateType = $type; }
    public function setAggregateVersion(int $version): void { $this->aggregateVersion = $version; }
    public function setOccurredAt(int $timestamp): void { $this->occurredAt = $timestamp; }
    public function setMetadata(array $metadata): void { $this->metadata = $metadata; }
}
