<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Observers;

use Core\Support\Facades\Log;
use Modules\Cms\Application\Services\PageBlockAggregateService;
use Modules\Cms\Infrastructure\Models\PageBlock;

/**
 * Page Block Event Sourcing Observer
 *
 * Automatically records events when PageBlock model changes
 */
class PageBlockEventSourcingObserver
{
    public function __construct(
        private PageBlockAggregateService $blockService,
    ) {
    }

    public function created(PageBlock $block): void
    {
        if (!$this->shouldRecord()) {
            return;
        }

        try {
            $this->blockService->createBlock(
                pageId: (string) $block->page_id,
                componentClass: $block->component_class,
                sortOrder: $block->sort_order,
                userId: $this->getCurrentUserId(),
            );

            Log::channel('event_sourcing')->info('Block created via Event Sourcing', [
                'block_id' => $block->id,
                'page_id' => $block->page_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Event Sourcing error on block create', [
                'error' => $e->getMessage(),
                'block_id' => $block->id,
            ]);
        }
    }

    public function updated(PageBlock $block): void
    {
        if (!$this->shouldRecord()) {
            return;
        }

        try {
            $userId = $this->getCurrentUserId();

            if ($block->isDirty('content')) {
                $this->blockService->updateBlockContent(
                    blockId: (string) $block->id,
                    content: $block->content ?? [],
                    userId: $userId,
                );
            }

            if ($block->isDirty('sort_order')) {
                $this->blockService->changeBlockOrder(
                    blockId: (string) $block->id,
                    newOrder: $block->sort_order,
                    userId: $userId,
                );
            }
        } catch (\Exception $e) {
            Log::error('Event Sourcing error on block update', [
                'error' => $e->getMessage(),
                'block_id' => $block->id,
            ]);
        }
    }

    public function deleted(PageBlock $block): void
    {
        if (!$this->shouldRecord()) {
            return;
        }

        try {
            $this->blockService->deleteBlock(
                blockId: (string) $block->id,
                userId: $this->getCurrentUserId(),
            );

            Log::channel('event_sourcing')->info('Block deleted via Event Sourcing', [
                'block_id' => $block->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Event Sourcing error on block delete', [
                'error' => $e->getMessage(),
                'block_id' => $block->id,
            ]);
        }
    }

    private function shouldRecord(): bool
    {
        return config('event-sourcing.dual_write', true)
            && config('event-sourcing.auto_record.enabled', true);
    }

    private function getCurrentUserId(): string
    {
        if (function_exists('auth') && auth()->check()) {
            return (string) auth()->id();
        }

        if (function_exists('request') && request()->user()) {
            return (string) request()->user()->id;
        }

        return 'system';
    }
}
