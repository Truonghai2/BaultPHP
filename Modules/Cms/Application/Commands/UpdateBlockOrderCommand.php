<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Modules\Cms\Infrastructure\Models\Page;

class UpdateBlockOrderCommand implements Command
{
    public function __construct(
        public readonly Page $page,
        public readonly array $orderedIds,
    ) {
    }
}
