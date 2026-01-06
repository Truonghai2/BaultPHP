<?php

declare(strict_types=1);

namespace Modules\Admin\Domain\Aggregates\Events;

use Core\EventSourcing\Events\DomainEvent;
use DateTimeImmutable;

class ModuleDependenciesResolved extends DomainEvent
{
    public function __construct(
        public readonly string $moduleId,
        public readonly array $dependencies,
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
            moduleId: $data['moduleId'],
            dependencies: $data['dependencies'],
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
            'moduleId' => $this->moduleId,
            'dependencies' => $this->dependencies,
            'occurredAt' => $this->occurredAt->format('Y-m-d H:i:s.u'),
            'eventVersion' => $this->eventVersion,
            'metadata' => $this->metadata,
        ];
    }
}
