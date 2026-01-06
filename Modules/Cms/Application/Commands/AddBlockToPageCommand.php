<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Modules\Cms\Infrastructure\Models\Page;

class AddBlockToPageCommand implements Command
{
    /**
     * @param Page $page The page to add the block to.
     * @param string $componentClass The class of the block component.
     */
    public function __construct(
        public readonly Page $page,
        public readonly string $componentClass,
    ) {
    }
}
