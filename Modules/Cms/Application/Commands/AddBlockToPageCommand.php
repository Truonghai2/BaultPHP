<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * @property-read \Modules\Cms\Infrastructure\Models\Page $page
 * @property-read string $componentClass
 */
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
