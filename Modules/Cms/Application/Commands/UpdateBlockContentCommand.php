<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;

class UpdateBlockContentCommand implements Command
{
    /**
     * @param int $pageBlockId ID của PageBlock cần cập nhật.
     * @param array<string, mixed> $data Dữ liệu nội dung mới.
     */
    public function __construct(
        public readonly int $pageBlockId,
        public readonly array $data,
    ) {
    }
}
