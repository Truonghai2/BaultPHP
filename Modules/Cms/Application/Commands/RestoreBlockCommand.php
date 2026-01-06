<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;

class RestoreBlockCommand implements Command
{
    /**
     * @param int $pageId The ID of the page to restore the block to.
     * @param string $componentClass The class of the block component.
     * @param array<string, mixed> $content The content of the block.
     * @param int $order The order of the block.
     */
    public function __construct(
        public readonly int $pageId,
        public readonly string $componentClass,
        public readonly array $content,
        public readonly int $order,
    ) {
    }
}
