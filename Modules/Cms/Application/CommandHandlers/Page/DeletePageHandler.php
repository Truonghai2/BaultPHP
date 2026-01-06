<?php

namespace Modules\Cms\Application\CommandHandlers\Page;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * DeletePageHandler
 * 
 * Handles the DeletePageCommand.
 */
class DeletePageHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): bool
    {
        $page = Page::find($command->pageId);
        
        if (!$page) {
            throw new \Exception("Page with ID {$command->pageId} not found");
        }

        $pageName = $page->name;
        $pageSlug = $page->slug;

        $page->delete();

        Audit::log(
            'data_change',
            "Page deleted: {$pageName}",
            [
                'page_id' => $command->pageId,
                'slug' => $pageSlug,
                'action' => 'page_deleted'
            ],
            'warning'
        );

        return true;
    }
}

