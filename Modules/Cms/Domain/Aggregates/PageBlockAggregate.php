<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Aggregates;

use Core\EventSourcing\AggregateRoot;
use Modules\Cms\Domain\Aggregates\Events\BlockContentUpdated;
use Modules\Cms\Domain\Aggregates\Events\BlockCreated;
use Modules\Cms\Domain\Aggregates\Events\BlockDeleted;
use Modules\Cms\Domain\Aggregates\Events\BlockDuplicated;
use Modules\Cms\Domain\Aggregates\Events\BlockOrderChanged;
use Modules\Cms\Domain\Aggregates\Events\BlockRestored;

/**
 * Page Block Aggregate
 *
 * Event-sourced aggregate for page blocks.
 * Tracks all changes to block content and positioning.
 *
 * Features:
 * - Full audit trail of content changes
 * - Track order/position changes
 * - Duplication history
 * - Soft delete & restore
 */
class PageBlockAggregate extends AggregateRoot
{
    private string $pageId;
    private string $componentClass;
    private int $sortOrder;
    private array $content = [];
    private bool $isDeleted = false;
    private ?\DateTimeImmutable $deletedAt = null;

    /**
     * Create a new block
     */
    public function create(
        string $id,
        string $pageId,
        string $componentClass,
        int $sortOrder,
        string $userId,
    ): void {
        $this->recordThat(new BlockCreated(
            blockId: $id,
            pageId: $pageId,
            componentClass: $componentClass,
            sortOrder: $sortOrder,
            userId: $userId,
        ));
    }

    /**
     * Update block content
     */
    public function updateContent(array $content, string $userId): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('Cannot update deleted block');
        }

        $oldContent = $this->content;

        $this->recordThat(new BlockContentUpdated(
            blockId: $this->id,
            oldContent: $oldContent,
            newContent: $content,
            userId: $userId,
        ));
    }

    /**
     * Change block order/position
     */
    public function changeOrder(int $newOrder, string $userId): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('Cannot reorder deleted block');
        }

        if ($this->sortOrder === $newOrder) {
            return; // No change
        }

        $this->recordThat(new BlockOrderChanged(
            blockId: $this->id,
            oldOrder: $this->sortOrder,
            newOrder: $newOrder,
            userId: $userId,
        ));
    }

    /**
     * Duplicate this block
     */
    public function duplicate(string $newBlockId, int $newOrder, string $userId): void
    {
        if ($this->isDeleted) {
            throw new \DomainException('Cannot duplicate deleted block');
        }

        $this->recordThat(new BlockDuplicated(
            originalBlockId: $this->id,
            newBlockId: $newBlockId,
            pageId: $this->pageId,
            componentClass: $this->componentClass,
            content: $this->content,
            sortOrder: $newOrder,
            userId: $userId,
        ));
    }

    /**
     * Delete the block (soft delete)
     */
    public function delete(string $userId): void
    {
        if ($this->isDeleted) {
            return; // Already deleted
        }

        $this->recordThat(new BlockDeleted(
            blockId: $this->id,
            userId: $userId,
        ));
    }

    /**
     * Restore a deleted block
     */
    public function restore(string $userId): void
    {
        if (!$this->isDeleted) {
            throw new \DomainException('Can only restore deleted blocks');
        }

        $this->recordThat(new BlockRestored(
            blockId: $this->id,
            userId: $userId,
        ));
    }

    // ==================== Event Application Methods ====================

    protected function applyBlockCreated(BlockCreated $event): void
    {
        $this->id = $event->blockId;
        $this->pageId = $event->pageId;
        $this->componentClass = $event->componentClass;
        $this->sortOrder = $event->sortOrder;
        $this->content = [];
        $this->isDeleted = false;
    }

    protected function applyBlockContentUpdated(BlockContentUpdated $event): void
    {
        $this->content = $event->newContent;
    }

    protected function applyBlockOrderChanged(BlockOrderChanged $event): void
    {
        $this->sortOrder = $event->newOrder;
    }

    protected function applyBlockDuplicated(BlockDuplicated $event): void
    {
        // This event is primarily for the new block aggregate
        // The original block doesn't change state
    }

    protected function applyBlockDeleted(BlockDeleted $event): void
    {
        $this->isDeleted = true;
        $this->deletedAt = $event->occurredAt;
    }

    protected function applyBlockRestored(BlockRestored $event): void
    {
        $this->isDeleted = false;
        $this->deletedAt = null;
    }

    // ==================== Getters ====================

    public function getPageId(): string
    {
        return $this->pageId;
    }

    public function getComponentClass(): string
    {
        return $this->componentClass;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }
}
