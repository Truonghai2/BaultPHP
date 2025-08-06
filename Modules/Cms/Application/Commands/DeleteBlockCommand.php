<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;

class DeleteBlockCommand implements Command
{
    /**
     * @param int $pageBlockId ID của PageBlock cần xóa.
     */
    public function __construct(
        public readonly int $pageBlockId,
    ) {
    }
}
