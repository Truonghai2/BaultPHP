<?php

namespace Modules\Cms\Application\CommandHandlers\Page;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * UpdatePageHandler
 *
 * Handles the UpdatePageCommand.
 */
class UpdatePageHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): bool
    {
        $page = Page::find($command->pageId);

        if (!$page) {
            throw new \Exception("Page with ID {$command->pageId} not found");
        }

        $oldSlug = $page->slug;

        // Update fields
        if ($command->name !== null) {
            $page->name = $command->name;
        }

        if ($command->slug !== null && $command->slug !== $oldSlug) {
            // Check slug uniqueness
            if (Page::where('slug', '=', $command->slug)
                    ->where('id', '!=', $command->pageId)
                    ->exists()) {
                throw new \Exception("Slug '{$command->slug}' is already in use");
            }

            $page->slug = $command->slug;
        }

        if ($command->status !== null) {
            $page->status = $command->status;
        }

        if ($command->metaTitle !== null) {
            $page->meta_title = $command->metaTitle;
        }

        if ($command->metaDescription !== null) {
            $page->meta_description = $command->metaDescription;
        }

        $page->save();

        // Additional audit (update is auto-logged)
        Audit::log(
            'data_change',
            "Page updated: {$page->name}",
            [
                'page_id' => $page->id,
                'slug' => $page->slug,
                'action' => 'page_updated',
            ],
            'info',
        );

        return true;
    }
}
