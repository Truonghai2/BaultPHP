<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Aggregates\Events;

use Core\EventSourcing\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Block Added To Page Event
 * 
 * Emitted when a new block is added to a page
 */
class BlockAddedToPage extends DomainEvent
{
    public function __construct(
        public readonly string $pageId,
        public readonly string $blockId,
        public readonly string $componentClass,
        public readonly int $sortOrder,
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
            blockId: $data['blockId'],
            componentClass: $data['componentClass'],
            sortOrder: $data['sortOrder'],
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
            'blockId' => $this->blockId,
            'componentClass' => $this->componentClass,
            'sortOrder' => $this->sortOrder,
            'userId' => $this->userId,
            'occurredAt' => $this->occurredAt->format('Y-m-d H:i:s.u'),
            'eventVersion' => $this->eventVersion,
            'metadata' => $this->metadata,
        ];
    }
}

