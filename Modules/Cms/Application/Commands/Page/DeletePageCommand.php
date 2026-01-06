<?php

namespace Modules\Cms\Application\Commands\Page;

use Core\CQRS\Contracts\CommandInterface;

/**
 * DeletePageCommand
 *
 * Command to delete a page.
 *
 * @property-read int $pageId
 */
class DeletePageCommand implements CommandInterface
{
    public function __construct(
        public readonly int $pageId,
    ) {
    }

    public function getCommandName(): string
    {
        return 'cms.page.delete';
    }
}
