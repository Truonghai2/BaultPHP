<?php

namespace Core\EventSourcing\Examples\Events;

use Core\EventSourcing\Events\DomainEvent;
use DateTimeImmutable;

class UserStatusChanged extends DomainEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
        int $eventVersion = 1,
        array $metadata = [],
    ) {
        parent::__construct($eventId, $occurredAt, $eventVersion, $metadata);
    }

    public static function fromArray(array $data): static
    {
        return new static(
            userId: $data['userId'],
            oldStatus: $data['oldStatus'],
            newStatus: $data['newStatus'],
            eventId: $data['eventId'],
            occurredAt: new DateTimeImmutable($data['occurredAt']),
            eventVersion: $data['eventVersion'] ?? 1,
            metadata: $data['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'eventId' => $this->eventId,
            'userId' => $this->userId,
            'oldStatus' => $this->oldStatus,
            'newStatus' => $this->newStatus,
            'occurredAt' => $this->occurredAt->format('Y-m-d H:i:s.u'),
            'eventVersion' => $this->eventVersion,
            'metadata' => $this->metadata,
        ];
    }
}
