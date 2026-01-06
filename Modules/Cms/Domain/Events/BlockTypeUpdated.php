<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Events;

use Modules\Cms\Infrastructure\Models\BlockType;

/**
 * Block Type Updated Event
 * 
 * Fired when a block type definition is changed
 */
class BlockTypeUpdated
{
    public function __construct(
        public readonly BlockType $blockType,
        public readonly string $action // 'created', 'updated', 'deleted', 'synced'
    ) {
    }
}

