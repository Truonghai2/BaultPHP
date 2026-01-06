<?php

declare(strict_types=1);

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;

/**
 * Update Page Content Command
 *
 * Command để cập nhật nội dung page
 */
final class UpdatePageContentCommand implements Command
{
    public function __construct(
        public readonly int $pageId,
        public readonly array $blocks,
        public readonly ?string $featuredImagePath = null,
        public readonly ?int $userId = null,
    ) {
    }
}
