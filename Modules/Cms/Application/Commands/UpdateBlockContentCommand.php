<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;

/**
 * @property-read int $pageBlockId
 * @property-read array<string, mixed> $data
 * @property-read mixed $content
 */
class UpdateBlockContentCommand implements Command
{
    /**
     * @param int $pageBlockId ID of the PageBlock to update.
     * @param array<string, mixed> $data The new content data.
     */
    public function __construct(
        public readonly int $pageBlockId,
        public readonly array $data,
    ) {
    }
}
