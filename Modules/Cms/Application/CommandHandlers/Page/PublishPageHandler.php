<?php

namespace Modules\Cms\Application\CommandHandlers\Page;

use Carbon\Carbon;
use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * PublishPageHandler
 * 
 * Handles the PublishPageCommand.
 */
class PublishPageHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): bool
    {
        $page = Page::find($command->pageId);
        
        if (!$page) {
            throw new \Exception("Page with ID {$command->pageId} not found");
        }

        // Check if already published
        if ($page->status === Page::STATUS_PUBLISHED) {
            return true; // Already published
        }

        // Update status
        $oldStatus = $page->status;
        $page->status = Page::STATUS_PUBLISHED;
        $page->published_at = Carbon::now();
        $page->save();

        // Audit log
        Audit::log(
            'data_change',
            "Page published: {$page->name}",
            [
                'page_id' => $page->id,
                'slug' => $page->slug,
                'old_status' => $oldStatus,
                'new_status' => Page::STATUS_PUBLISHED,
                'action' => 'page_published'
            ],
            'info'
        );

        return true;
    }
}

