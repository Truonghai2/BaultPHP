<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Listeners;

use Modules\Cms\Domain\Events\BlockTypeUpdated;
use Modules\Cms\Domain\Events\BlockUpdated;
use Modules\Cms\Domain\Events\PageUpdated;
use Modules\Cms\Domain\Services\BlockCacheManager;
use Psr\Log\LoggerInterface;

/**
 * Invalidate Block Cache Listener
 *
 * Automatically invalidates relevant caches when blocks, pages, or block types change
 */
class InvalidateBlockCache
{
    public function __construct(
        private readonly BlockCacheManager $cacheManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Handle block updated event
     */
    public function handleBlockUpdated(BlockUpdated $event): void
    {
        $this->logger->debug('Cache invalidation triggered by BlockUpdated event', [
            'block_id' => $event->block->id,
            'action' => $event->action,
        ]);

        $this->cacheManager->invalidateBlock($event->block);
    }

    /**
     * Handle page updated event
     */
    public function handlePageUpdated(PageUpdated $event): void
    {
        $this->logger->debug('Cache invalidation triggered by PageUpdated event', [
            'page_id' => $event->page->id,
            'action' => $event->action,
        ]);

        $this->cacheManager->invalidatePage($event->page);
    }

    /**
     * Handle block type updated event
     */
    public function handleBlockTypeUpdated(BlockTypeUpdated $event): void
    {
        $this->logger->info('Cache invalidation triggered by BlockTypeUpdated event', [
            'block_type_id' => $event->blockType->id,
            'action' => $event->action,
        ]);

        $this->cacheManager->invalidateBlockType($event->blockType);
    }
}
