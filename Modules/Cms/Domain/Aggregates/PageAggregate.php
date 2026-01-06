<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Aggregates;

use Core\EventSourcing\AggregateRoot;
use Modules\Cms\Domain\Aggregates\Events\BlockAddedToPage;
use Modules\Cms\Domain\Aggregates\Events\PageContentUpdated;
use Modules\Cms\Domain\Aggregates\Events\PageCreated;
use Modules\Cms\Domain\Aggregates\Events\PageDeleted;
use Modules\Cms\Domain\Aggregates\Events\PagePublished;
use Modules\Cms\Domain\Aggregates\Events\PageRenamed;
use Modules\Cms\Domain\Aggregates\Events\PageRestored;
use Modules\Cms\Domain\Aggregates\Events\PageUnpublished;
use Modules\Cms\Domain\Aggregates\Events\FeaturedImageChanged;
use Modules\Cms\Domain\ValueObjects\PageContent;

/**
 * Page Aggregate
 * 
 * Event-sourced aggregate for CMS pages.
 * Maintains page state through domain events.
 * 
 * Features:
 * - Complete audit trail of all changes
 * - Time travel to any version
 * - Concurrent editing with conflict detection
 * - Draft/publish workflows
 */
class PageAggregate extends AggregateRoot
{
    private string $name;
    private string $slug;
    private ?int $userId = null;
    private array $content = [];
    private ?string $featuredImagePath = null;
    private string $status = 'draft'; // draft, published, deleted
    private bool $isPublished = false;
    private ?\DateTimeImmutable $publishedAt = null;
    private ?\DateTimeImmutable $deletedAt = null;
    private array $blockIds = [];

    /**
     * Create a new page
     */
    public function create(
        string $id,
        string $name,
        string $slug,
        ?int $userId = null
    ): void {
        $this->recordThat(new PageCreated(
            pageId: $id,
            name: $name,
            slug: $slug,
            userId: $userId
        ));
    }

    /**
     * Update page content
     */
    public function updateContent(array $content, string $userId): void
    {
        if ($this->status === 'deleted') {
            throw new \DomainException('Cannot update deleted page');
        }

        $oldContent = $this->content;

        $this->recordThat(new PageContentUpdated(
            pageId: $this->id,
            oldContent: $oldContent,
            newContent: $content,
            userId: $userId
        ));
    }

    /**
     * Rename the page
     */
    public function rename(string $newName, string $newSlug, string $userId): void
    {
        if ($this->status === 'deleted') {
            throw new \DomainException('Cannot rename deleted page');
        }

        if ($this->name === $newName && $this->slug === $newSlug) {
            return; // No change needed
        }

        $this->recordThat(new PageRenamed(
            pageId: $this->id,
            oldName: $this->name,
            newName: $newName,
            oldSlug: $this->slug,
            newSlug: $newSlug,
            userId: $userId
        ));
    }

    /**
     * Publish the page
     */
    public function publish(string $userId): void
    {
        if ($this->isPublished) {
            return; // Already published
        }

        if ($this->status === 'deleted') {
            throw new \DomainException('Cannot publish deleted page');
        }

        $this->recordThat(new PagePublished(
            pageId: $this->id,
            userId: $userId
        ));
    }

    /**
     * Unpublish the page
     */
    public function unpublish(string $userId): void
    {
        if (!$this->isPublished) {
            return; // Already unpublished
        }

        $this->recordThat(new PageUnpublished(
            pageId: $this->id,
            userId: $userId
        ));
    }

    /**
     * Delete the page (soft delete)
     */
    public function delete(string $userId, string $reason = ''): void
    {
        if ($this->status === 'deleted') {
            return; // Already deleted
        }

        $this->recordThat(new PageDeleted(
            pageId: $this->id,
            userId: $userId,
            reason: $reason
        ));
    }

    /**
     * Restore a deleted page
     */
    public function restore(string $userId): void
    {
        if ($this->status !== 'deleted') {
            throw new \DomainException('Can only restore deleted pages');
        }

        $this->recordThat(new PageRestored(
            pageId: $this->id,
            userId: $userId
        ));
    }

    /**
     * Change featured image
     */
    public function changeFeaturedImage(?string $imagePath, string $userId): void
    {
        if ($this->status === 'deleted') {
            throw new \DomainException('Cannot change image of deleted page');
        }

        if ($this->featuredImagePath === $imagePath) {
            return; // No change
        }

        $this->recordThat(new FeaturedImageChanged(
            pageId: $this->id,
            oldPath: $this->featuredImagePath,
            newPath: $imagePath,
            userId: $userId
        ));
    }

    /**
     * Add a block to the page
     */
    public function addBlock(string $blockId, string $componentClass, int $sortOrder, string $userId): void
    {
        if ($this->status === 'deleted') {
            throw new \DomainException('Cannot add block to deleted page');
        }

        if (in_array($blockId, $this->blockIds)) {
            throw new \DomainException("Block {$blockId} already exists on page");
        }

        $this->recordThat(new BlockAddedToPage(
            pageId: $this->id,
            blockId: $blockId,
            componentClass: $componentClass,
            sortOrder: $sortOrder,
            userId: $userId
        ));
    }

    // ==================== Event Application Methods ====================

    protected function applyPageCreated(PageCreated $event): void
    {
        $this->id = $event->pageId;
        $this->name = $event->name;
        $this->slug = $event->slug;
        $this->userId = $event->userId;
        $this->status = 'draft';
        $this->isPublished = false;
        $this->content = [];
        $this->blockIds = [];
    }

    protected function applyPageContentUpdated(PageContentUpdated $event): void
    {
        $this->content = $event->newContent;
    }

    protected function applyPageRenamed(PageRenamed $event): void
    {
        $this->name = $event->newName;
        $this->slug = $event->newSlug;
    }

    protected function applyPagePublished(PagePublished $event): void
    {
        $this->status = 'published';
        $this->isPublished = true;
        $this->publishedAt = $event->occurredAt;
    }

    protected function applyPageUnpublished(PageUnpublished $event): void
    {
        $this->status = 'draft';
        $this->isPublished = false;
        $this->publishedAt = null;
    }

    protected function applyPageDeleted(PageDeleted $event): void
    {
        $this->status = 'deleted';
        $this->isPublished = false;
        $this->deletedAt = $event->occurredAt;
    }

    protected function applyPageRestored(PageRestored $event): void
    {
        $this->status = 'draft';
        $this->deletedAt = null;
    }

    protected function applyFeaturedImageChanged(FeaturedImageChanged $event): void
    {
        $this->featuredImagePath = $event->newPath;
    }

    protected function applyBlockAddedToPage(BlockAddedToPage $event): void
    {
        $this->blockIds[] = $event->blockId;
    }

    // ==================== Getters ====================

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function getFeaturedImagePath(): ?string
    {
        return $this->featuredImagePath;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function isDeleted(): bool
    {
        return $this->status === 'deleted';
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function getBlockIds(): array
    {
        return $this->blockIds;
    }

    /**
     * Check if user owns this page
     */
    public function isOwnedBy(int $userId): bool
    {
        return $this->userId === $userId;
    }
}

