<?php

namespace Modules\Cms\Application\Commands\Page;

use Core\CQRS\Contracts\CommandInterface;

/**
 * UpdatePageCommand
 *
 * Command to update a page.
 */
class UpdatePageCommand implements CommandInterface
{
    public function __construct(
        public readonly int $pageId,
        public readonly ?string $name = null,
        public readonly ?string $slug = null,
        public readonly ?string $status = null,
        public readonly ?string $metaTitle = null,
        public readonly ?string $metaDescription = null,
    ) {
    }

    public function getCommandName(): string
    {
        return 'cms.page.update';
    }
}
