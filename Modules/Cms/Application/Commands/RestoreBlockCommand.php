<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;

class RestoreBlockCommand implements Command
{
    /**
     * @param int $pageId
     * @param string $componentClass
     * @param array<string, mixed> $content
     * @param int $order
     */
    public function __construct(
        public readonly int $pageId,
        public readonly string $componentClass,
        public readonly array $content,
        public readonly int $order,
    ) {
    }
}
