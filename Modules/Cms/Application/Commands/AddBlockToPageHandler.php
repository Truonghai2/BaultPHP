<?php

declare(strict_types=1);

namespace Modules\Cms\Application\Commands;

use App\Exceptions\AuthorizationException;
use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Support\Facades\Auth;
use Modules\Cms\Domain\Entities\PageBlock;
use Modules\Cms\Domain\Services\PageBlockService;

class AddBlockToPageHandler implements CommandHandler
{
    public function __construct(
        private readonly PageBlockService $pageBlockService,
    ) {
    }

    /**
     * Handle the command to add a new block to a page.
     *
     * @param Command|AddBlockToPageCommand $command
     * @return PageBlock The newly created block.
     * @throws AuthorizationException
     */
    public function handle(Command $command): PageBlock
    {
        /** @var AddBlockToPageCommand $command */
        /** @var \Modules\User\Infrastructure\Models\User|null $user */
        $user = Auth::user();

        $user->can('update', $command->page);

        return $this->pageBlockService->addBlockToPage(
            $command->page->id,
            $command->componentClass,
        );
    }
}
