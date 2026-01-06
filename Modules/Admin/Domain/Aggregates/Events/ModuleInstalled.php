<?php

declare(strict_types=1);

namespace Modules\Admin\Domain\Aggregates\Events;

use Core\EventSourcing\Events\DomainEvent;
use DateTimeImmutable;

class ModuleInstalled extends DomainEvent
{
    public function __construct(
        public readonly string $moduleId,
        public readonly string $name,
        public readonly string $version,
        public readonly array $dependencies = [],
        public readonly array $metadata = [],
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
        int $eventVersion = 1,
        array $eventMetadata = [],
    ) {
        parent::__construct($eventId, $occurredAt, $eventVersion, $eventMetadata);
    }

    public static function fromArray(array $data): static
    {
        return new static(
            moduleId: $data['moduleId'],
            name: $data['name'],
            version: $data['version'],
            dependencies: $data['dependencies'] ?? [],
            metadata: $data['metadata'] ?? [],
            eventId: $data['eventId'],
            occurredAt: new DateTimeImmutable($data['occurredAt']),
            eventVersion: $data['eventVersion'] ?? 1,
            eventMetadata: $data['eventMetadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'eventId' => $this->eventId,
            'moduleId' => $this->moduleId,
            'name' => $this->name,
            'version' => $this->version,
            'dependencies' => $this->dependencies,
            'metadata' => $this->metadata,
            'occurredAt' => $this->occurredAt->format('Y-m-d H:i:s.u'),
            'eventVersion' => $this->eventVersion,
            'eventMetadata' => $this->metadata,
        ];
    }
}
