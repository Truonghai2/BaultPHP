<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Events;

use Modules\Cms\Infrastructure\Models\Page;

/**
 * Page Updated Event
 *
 * Fired when a page is created, updated, or deleted
 */
class PageUpdated
{
    public function __construct(
        public readonly Page $page,
        public readonly string $action, // 'created', 'updated', 'deleted'
    ) {
    }
}
