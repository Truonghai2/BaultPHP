<?php

namespace Modules\Cms\Application\Commands\Page;

use Core\CQRS\Contracts\CommandInterface;

/**
 * CreatePageCommand
 *
 * Command to create a new page in the CMS.
 */
class CreatePageCommand implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly int $userId,
        public readonly string $status = 'draft',
        public readonly ?string $metaTitle = null,
        public readonly ?string $metaDescription = null,
    ) {
    }

    public function getCommandName(): string
    {
        return 'cms.page.create';
    }
}
