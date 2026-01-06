<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Observers;

use Modules\Cms\Application\Services\PageAggregateService;
use Modules\Cms\Infrastructure\Models\Page;
use Illuminate\Support\Facades\Log;

/**
 * Page Event Sourcing Observer
 * 
 * Automatically records events when Page model changes
 * Works alongside traditional database operations
 */
class PageEventSourcingObserver
{
    public function __construct(
        private PageAggregateService $pageService
    ) {
    }

    /**
     * Handle page created event
     */
    public function created(Page $page): void
    {
        if (!$this->shouldRecord()) {
            return;
        }

        try {
            // Use page ID from database as aggregate ID
            $this->pageService->createPage(
                name: $page->name,
                slug: $page->slug,
                userId: $page->user_id
            );

            Log::channel('event_sourcing')->info('Page created via Event Sourcing', [
                'page_id' => $page->id,
                'name' => $page->name
            ]);
        } catch (\Exception $e) {
            // Don't fail the main operation if event sourcing fails
            Log::error('Event Sourcing error on page create', [
                'error' => $e->getMessage(),
                'page_id' => $page->id
            ]);
        }
    }

    /**
     * Handle page updated event
     */
    public function updated(Page $page): void
    {
        if (!$this->shouldRecord()) {
            return;
        }

        try {
            // Get authenticated user ID
            $userId = $this->getCurrentUserId();

            // Check what was changed
            if ($page->isDirty('name') || $page->isDirty('slug')) {
                $this->pageService->renamePage(
                    pageId: (string) $page->id,
                    newName: $page->name,
                    newSlug: $page->slug,
                    userId: $userId
                );
            }

            if ($page->isDirty('content')) {
                $this->pageService->updatePageContent(
                    pageId: (string) $page->id,
                    content: $page->content ?? [],
                    userId: $userId
                );
            }

            if ($page->isDirty('featured_image_path')) {
                $this->pageService->changeFeaturedImage(
                    pageId: (string) $page->id,
                    imagePath: $page->featured_image_path,
                    userId: $userId
                );
            }

            // Check status changes
            if ($page->isDirty('status')) {
                $this->handleStatusChange($page, $userId);
            }

        } catch (\Exception $e) {
            Log::error('Event Sourcing error on page update', [
                'error' => $e->getMessage(),
                'page_id' => $page->id
            ]);
        }
    }

    /**
     * Handle page deleted event
     */
    public function deleted(Page $page): void
    {
        if (!$this->shouldRecord()) {
            return;
        }

        try {
            $this->pageService->deletePage(
                pageId: (string) $page->id,
                userId: $this->getCurrentUserId(),
                reason: 'Deleted from admin panel'
            );

            Log::channel('event_sourcing')->info('Page deleted via Event Sourcing', [
                'page_id' => $page->id
            ]);
        } catch (\Exception $e) {
            Log::error('Event Sourcing error on page delete', [
                'error' => $e->getMessage(),
                'page_id' => $page->id
            ]);
        }
    }

    /**
     * Handle status changes (publish/unpublish)
     */
    private function handleStatusChange(Page $page, string $userId): void
    {
        $oldStatus = $page->getOriginal('status');
        $newStatus = $page->status;

        if ($newStatus === 'published' && $oldStatus !== 'published') {
            $this->pageService->publishPage((string) $page->id, $userId);
        } elseif ($oldStatus === 'published' && $newStatus !== 'published') {
            $this->pageService->unpublishPage((string) $page->id, $userId);
        }
    }

    /**
     * Check if we should record events
     */
    private function shouldRecord(): bool
    {
        // Check if dual write mode is enabled
        if (!config('event-sourcing.dual_write', true)) {
            return false;
        }

        // Check if auto recording is enabled
        return config('event-sourcing.auto_record.enabled', true);
    }

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId(): string
    {
        // Try to get from auth
        if (function_exists('auth') && auth()->check()) {
            return (string) auth()->id();
        }

        // Try to get from request
        if (function_exists('request') && request()->user()) {
            return (string) request()->user()->id;
        }

        // Default to system user
        return 'system';
    }
}

