<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * @property-read \Modules\Cms\Infrastructure\Models\Page $page
 * @property-read array $orderedIds
 * @property-read array $blockOrders
 */
class UpdateBlockOrderCommand implements Command
{
    public function __construct(
        public readonly Page $page,
        public readonly array $orderedIds,
    ) {
    }
}
