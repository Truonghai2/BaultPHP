<?php

declare(strict_types=1);

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Support\Facades\Auth;
use Modules\Cms\Domain\Exceptions\PageBlockNotFoundException;
use Modules\Cms\Domain\Repositories\PageBlockRepositoryInterface;
use Modules\Cms\Domain\Services\PageBlockService;
use Modules\Cms\Domain\ValueObjects\PageBlockId;

class UpdateBlockContentHandler implements CommandHandler
{
    public function __construct(
        private readonly PageBlockService $pageBlockService,
        private readonly PageBlockRepositoryInterface $pageBlockRepository
    ) {
    }

    /**
     * Handle the command to update the content of a block.
     *
     * @param Command|UpdateBlockContentCommand $command
     * @return array<string, mixed> The old content of the block.
     * @throws PageBlockNotFoundException
     */
    public function handle(Command $command): array
    {
        /** @var UpdateBlockContentCommand $command */
        $blockId = new PageBlockId($command->pageBlockId);

        /** @var \Modules\User\Infrastructure\Models\User $user */
        $user = Auth::user();

        // Get Eloquent model for policy check
        $blockModel = \Modules\Cms\Infrastructure\Models\PageBlock::find($command->pageBlockId);
        if ($blockModel) {
            $user->can('update', $blockModel);
        }

        return $this->pageBlockService->updateBlockContent($blockId, $command->content);
    }
}
