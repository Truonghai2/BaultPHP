<?php

declare(strict_types=1);

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Support\Facades\Auth;
use Modules\Cms\Domain\Services\PageBlockService;

class UpdateBlockOrderHandler implements CommandHandler
{
    public function __construct(
        private readonly PageBlockService $pageBlockService,
    ) {
    }

    /**
     * Handle the command to update the order of blocks for a page.
     *
     * @param Command|UpdateBlockOrderCommand $command
     * @return void
     */
    public function handle(Command $command): void
    {
        /** @var UpdateBlockOrderCommand $command */
        /** @var \Modules\User\Infrastructure\Models\User $user */
        $user = Auth::user();

        $user->can('update', $command->page);

        $this->pageBlockService->updateBlockOrder(
            $command->page->id,
            $command->orderedIds,
        );
    }
}
