<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Observers;

use Modules\Cms\Domain\Events\BlockUpdated;
use Modules\Cms\Infrastructure\Models\PageBlock;

/**
 * PageBlock Model Observer
 *
 * Fires events when PageBlock models are modified
 */
class PageBlockObserver
{
    public function created(PageBlock $pageBlock): void
    {
        $this->fireEvent($pageBlock, 'created');
    }

    public function updated(PageBlock $pageBlock): void
    {
        $this->fireEvent($pageBlock, 'updated');
    }

    public function deleted(PageBlock $pageBlock): void
    {
        $this->fireEvent($pageBlock, 'deleted');
    }

    private function fireEvent(PageBlock $pageBlock, string $action): void
    {
        try {
            event(new BlockUpdated($pageBlock, $action));
        } catch (\Throwable $e) {
            // Log but don't fail the operation
            if (function_exists('logger')) {
                logger()->error('Failed to fire BlockUpdated event', [
                    'page_block_id' => $pageBlock->id,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
