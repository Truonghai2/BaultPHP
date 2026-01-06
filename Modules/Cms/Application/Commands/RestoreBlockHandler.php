<?php

declare(strict_types=1);

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Support\Facades\Auth;
use Modules\Cms\Domain\Exceptions\PageNotFoundException;
use Modules\Cms\Domain\Services\PageBlockService;
use Modules\Cms\Infrastructure\Models\Page;

class RestoreBlockHandler implements CommandHandler
{
    public function __construct(
        private readonly PageBlockService $pageBlockService,
    ) {
    }

    /**
     * Handle the command to restore a deleted block.
     *
     * @param Command|RestoreBlockCommand $command
     * @return void
     */
    public function handle(Command $command): void
    {
        /** @var RestoreBlockCommand $command */

        /** @var \Modules\User\Infrastructure\Models\User $user */
        $user = Auth::user();

        $page = Page::find($command->pageId);
        if (!$page) {
            throw new PageNotFoundException("Page with ID {$command->pageId} not found.");
        }
        $user->can('update', $page);

        $this->pageBlockService->restoreBlock(
            $command->pageId,
            $command->componentClass,
            $command->order,
            $command->content,
        );
    }
}
