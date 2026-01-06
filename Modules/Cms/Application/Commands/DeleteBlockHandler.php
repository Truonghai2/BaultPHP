<?php

declare(strict_types=1);

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Support\Facades\Auth;
use Modules\Cms\Domain\Entities\PageBlock;
use Modules\Cms\Domain\Exceptions\PageBlockNotFoundException;
use Modules\Cms\Domain\Repositories\PageBlockRepositoryInterface;
use Modules\Cms\Domain\Services\PageBlockService;
use Modules\Cms\Domain\ValueObjects\PageBlockId;

class DeleteBlockHandler implements CommandHandler
{
    public function __construct(
        private readonly PageBlockService $pageBlockService,
        private readonly PageBlockRepositoryInterface $pageBlockRepository
    ) {
    }

    /**
     * Handle the command to delete a block.
     *
     * @param Command|DeleteBlockCommand $command
     * @return PageBlock The deleted block to allow for undo.
     * @throws PageBlockNotFoundException|\App\Exceptions\AuthorizationException
     */
    public function handle(Command $command): PageBlock
    {
        /** @var DeleteBlockCommand $command */
        $blockId = new PageBlockId($command->pageBlockId);
        $block = $this->pageBlockRepository->findById($blockId);

        /** @var \Modules\User\Infrastructure\Models\User $user */
        $user = Auth::user();

        // Get Eloquent model for policy check
        $blockModel = \Modules\Cms\Infrastructure\Models\PageBlock::find($command->pageBlockId);
        if ($blockModel) {
            $user->can('delete', $blockModel);
        }

        return $this->pageBlockService->deleteBlock($blockId);
    }
}
