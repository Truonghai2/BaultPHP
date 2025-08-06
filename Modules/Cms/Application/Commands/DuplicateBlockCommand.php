<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;

class DuplicateBlockCommand implements Command
{
    /**
     * @param int $pageBlockId ID của PageBlock cần sao chép.
     */
    public function __construct(
        public readonly int $pageBlockId,
    ) {
    }
}
