<?php

declare(strict_types=1);

namespace Modules\Cms\Application\Projections;

use Modules\Cms\Domain\Aggregates\Events\PageCreated;
use Modules\Cms\Domain\Aggregates\Events\PageDeleted;
use Modules\Cms\Domain\Aggregates\Events\PagePublished;
use Modules\Cms\Domain\Aggregates\Events\PageRenamed;
use Modules\Cms\Domain\Aggregates\Events\PageUnpublished;
use Modules\Cms\Domain\Aggregates\Events\PageRestored;
use Modules\Cms\Infrastructure\Models\PageListItem;

/**
 * Page List Projection
 *
 * This projection listens to domain events from the PageAggregate and updates
 * a denormalized read model (`page_list_items` table) for fast queries.
 */
class PageListProjection
{
    /**
     * Handle PageCreated event
     */
    public function onPageCreated(PageCreated $event): void
    {
        PageListItem::updateOrCreate(
            ['page_uuid' => $event->pageId],
            [
                'name' => $event->name,
                'slug' => $event->slug,
                'author_id' => $event->userId,
                'status' => 'draft',
            ]
        );
    }

    /**
     * Handle PageRenamed event
     */
    public function onPageRenamed(PageRenamed $event): void
    {
        PageListItem::where('page_uuid', $event->pageId)->update([
            'name' => $event->newName,
            'slug' => $event->newSlug,
        ]);
    }

    /**
     * Handle PagePublished event
     */
    public function onPagePublished(PagePublished $event): void
    {
        PageListItem::where('page_uuid', $event->pageId)->update([
            'status' => 'published',
            'published_at' => $event->occurredAt,
        ]);
    }

    /**
     * Handle PageUnpublished event
     */
    public function onPageUnpublished(PageUnpublished $event): void
    {
        PageListItem::where('page_uuid', $event->pageId)->update([
            'status' => 'draft',
        ]);
    }

    /**
     * Handle PageDeleted event
     */
    public function onPageDeleted(PageDeleted $event): void
    {
        PageListItem::where('page_uuid', $event->pageId)->delete();
    }

    /**
     * Handle PageRestored event
     */
    public function onPageRestored(PageRestored $event): void
    {
        PageListItem::where('page_uuid', $event->pageId)->update(['deleted_at' => null]);
    }
}
