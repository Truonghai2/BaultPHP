<?php

namespace Modules\Cms\Application\CommandHandlers\Page;

use Core\CQRS\Contracts\CommandInterface;
use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\Support\Facades\Audit;
use Modules\Cms\Application\Commands\Page\CreatePageCommand;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * CreatePageHandler
 * 
 * Handles the CreatePageCommand.
 */
class CreatePageHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed
    {
        if (!$command instanceof CreatePageCommand) {
            throw new \InvalidArgumentException('CreatePageHandler can only handle CreatePageCommand.');
        }

        // Validate slug uniqueness
        if (Page::where('slug', '=', $command->slug)->exists()) {
            throw new \Exception("Slug '{$command->slug}' is already in use");
        }

        // Create page
        $page = Page::create([
            'name' => $command->name,
            'slug' => $command->slug,
            'user_id' => $command->userId,
            'status' => $command->status,
            'meta_title' => $command->metaTitle ?? $command->name,
            'meta_description' => $command->metaDescription,
        ]);

        // Additional audit log (creation is auto-logged)
        Audit::log(
            'data_change',
            "Page created: {$command->name}",
            [
                'page_id' => $page->id,
                'slug' => $command->slug,
                'status' => $command->status,
                'action' => 'page_created'
            ],
            'info'
        );

        return $page->id;
    }
}
