<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Aggregates\Events;

use Core\EventSourcing\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Page Content Updated Event
 * 
 * Emitted when page content is modified
 */
class PageContentUpdated extends DomainEvent
{
    public function __construct(
        public readonly string $pageId,
        public readonly array $oldContent,
        public readonly array $newContent,
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
            oldContent: $data['oldContent'],
            newContent: $data['newContent'],
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
            'oldContent' => $this->oldContent,
            'newContent' => $this->newContent,
            'userId' => $this->userId,
            'occurredAt' => $this->occurredAt->format('Y-m-d H:i:s.u'),
            'eventVersion' => $this->eventVersion,
            'metadata' => $this->metadata,
        ];
    }
}

