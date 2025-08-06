<?php

declare(strict_types=1);

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;

/**
 * Command để cập nhật nội dung của một trang.
 * Class này là một Data Transfer Object (DTO) đơn giản, chứa tất cả
 * dữ liệu cần thiết để thực thi hành động.
 */
class UpdatePageContentCommand implements Command
{
    public function __construct(
        public readonly int $pageId,
        public readonly array $blocks,
        public readonly ?string $featuredImagePath,
    ) {
    }
}
