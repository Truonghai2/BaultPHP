<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Observers;

use Modules\Cms\Domain\Events\BlockUpdated;
use Modules\Cms\Infrastructure\Models\BlockInstance;

/**
 * BlockInstance Model Observer
 *
 * Fires events when BlockInstance models are modified
 */
class BlockInstanceObserver
{
    public function created(BlockInstance $instance): void
    {
        $this->fireEvent($instance, 'created');
    }

    public function updated(BlockInstance $instance): void
    {
        $this->fireEvent($instance, 'updated');
    }

    public function deleted(BlockInstance $instance): void
    {
        $this->fireEvent($instance, 'deleted');
    }

    private function fireEvent(BlockInstance $instance, string $action): void
    {
        try {
            event(new BlockUpdated($instance, $action));
        } catch (\Throwable $e) {
            if (function_exists('logger')) {
                logger()->error('Failed to fire BlockUpdated event', [
                    'block_instance_id' => $instance->id,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
