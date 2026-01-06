<?php

namespace Modules\Cms\Application\Commands\Page;

use Core\CQRS\Contracts\CommandInterface;

/**
 * PublishPageCommand
 * 
 * Command to publish a page.
 */
class PublishPageCommand implements CommandInterface
{
    public function __construct(
        public readonly int $pageId
    ) {}

    public function getCommandName(): string
    {
        return 'cms.page.publish';
    }
}

