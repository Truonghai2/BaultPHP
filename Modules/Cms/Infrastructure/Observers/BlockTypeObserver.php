<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Observers;

use Modules\Cms\Domain\Events\BlockTypeUpdated;
use Modules\Cms\Infrastructure\Models\BlockType;

/**
 * BlockType Model Observer
 *
 * Fires events when BlockType models are modified (sync/update)
 */
class BlockTypeObserver
{
    public function created(BlockType $blockType): void
    {
        $this->fireEvent($blockType, 'created');
    }

    public function updated(BlockType $blockType): void
    {
        $this->fireEvent($blockType, 'updated');
    }

    public function deleted(BlockType $blockType): void
    {
        $this->fireEvent($blockType, 'deleted');
    }

    private function fireEvent(BlockType $blockType, string $action): void
    {
        try {
            event(new BlockTypeUpdated($blockType, $action));
        } catch (\Throwable $e) {
            if (function_exists('logger')) {
                logger()->error('Failed to fire BlockTypeUpdated event', [
                    'block_type_id' => $blockType->id,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
