<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;

/**
 * @property-read int $pageBlockId
 * @property-read int $blockId
 */
class DuplicateBlockCommand implements Command
{
    /**
     * @param int $pageBlockId ID of the PageBlock to duplicate.
     */
    public function __construct(
        public readonly int $pageBlockId,
    ) {
    }
}
