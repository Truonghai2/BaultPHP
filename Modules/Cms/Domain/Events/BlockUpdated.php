<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Events;

use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\Cms\Infrastructure\Models\PageBlock;

/**
 * Block Updated Event
 *
 * Fired when a block is created, updated, or deleted
 */
class BlockUpdated
{
    public function __construct(
        public readonly BlockInstance|PageBlock $block,
        public readonly string $action, // 'created', 'updated', 'deleted'
    ) {
    }
}
