<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Aggregates\Events;

use Core\EventSourcing\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Page Renamed Event
 * 
 * Emitted when page name or slug changes
 */
class PageRenamed extends DomainEvent
{
    public function __construct(
        public readonly string $pageId,
        public readonly string $oldName,
        public readonly string $newName,
        public readonly string $oldSlug,
        public readonly string $newSlug,
        public readonly string $userId,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
        int $eventVersion = 1,
        array $metadata = []
    ) {
        parent::__construct($eventId, $occurredAt, $eventVersion, $metadata);
    }

    public static function fromArray(array $data): static
    {
        return new static(
            pageId: $data['pageId'],
            oldName: $data['oldName'],
            newName: $data['newName'],
            oldSlug: $data['oldSlug'],
            newSlug: $data['newSlug'],
            userId: $data['userId'],
            eventId: $data['eventId'],
            occurredAt: new DateTimeImmutable($data['occurredAt']),
            eventVersion: $data['eventVersion'] ?? 1,
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'eventId' => $this->eventId,
            'pageId' => $this->pageId,
            'oldName' => $this->oldName,
            'newName' => $this->newName,
            'oldSlug' => $this->oldSlug,
            'newSlug' => $this->newSlug,
            'userId' => $this->userId,
            'occurredAt' => $this->occurredAt->format('Y-m-d H:i:s.u'),
            'eventVersion' => $this->eventVersion,
            'metadata' => $this->metadata,
        ];
    }
}

