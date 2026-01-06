<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Observers;

use Modules\Cms\Domain\Events\PageUpdated;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * Page Model Observer
 *
 * Fires events when Page models are modified
 */
class PageObserver
{
    public function created(Page $page): void
    {
        $this->fireEvent($page, 'created');
    }

    public function updated(Page $page): void
    {
        $this->fireEvent($page, 'updated');
    }

    public function deleted(Page $page): void
    {
        $this->fireEvent($page, 'deleted');
    }

    private function fireEvent(Page $page, string $action): void
    {
        try {
            event(new PageUpdated($page, $action));
        } catch (\Throwable $e) {
            if (function_exists('logger')) {
                logger()->error('Failed to fire PageUpdated event', [
                    'page_id' => $page->id,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
