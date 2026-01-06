<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command\Command;

/**
 * @property-read int $pageBlockId
 */
class DeleteBlockCommand implements Command
{
    /**
     * @param int $pageBlockId ID of the PageBlock to delete.
     */
    public function __construct(
        public readonly int $pageBlockId,
    ) {
    }
}
