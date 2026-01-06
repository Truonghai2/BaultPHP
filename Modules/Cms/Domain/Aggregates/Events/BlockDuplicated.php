<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Aggregates\Events;

use Core\EventSourcing\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Block Duplicated Event
 *
 * Emitted when a block is duplicated
 */
class BlockDuplicated extends DomainEvent
{
    public function __construct(
        public readonly string $originalBlockId,
        public readonly string $newBlockId,
        public readonly string $pageId,
        public readonly string $componentClass,
        public readonly array $content,
        public readonly int $sortOrder,
        public readonly string $userId,
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
            originalBlockId: $data['originalBlockId'],
            newBlockId: $data['newBlockId'],
            pageId: $data['pageId'],
            componentClass: $data['componentClass'],
            content: $data['content'],
            sortOrder: $data['sortOrder'],
            userId: $data['userId'],
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
            'originalBlockId' => $this->originalBlockId,
            'newBlockId' => $this->newBlockId,
            'pageId' => $this->pageId,
            'componentClass' => $this->componentClass,
            'content' => $this->content,
            'sortOrder' => $this->sortOrder,
            'userId' => $this->userId,
            'occurredAt' => $this->occurredAt->format('Y-m-d H:i:s.u'),
            'eventVersion' => $this->eventVersion,
            'metadata' => $this->metadata,
        ];
    }
}
