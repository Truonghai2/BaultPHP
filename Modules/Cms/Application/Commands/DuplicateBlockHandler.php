<?php

declare(strict_types=1);

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Support\Facades\Auth;
use Modules\Cms\Domain\Entities\PageBlock;
use Modules\Cms\Domain\Exceptions\PageBlockNotFoundException;
use Modules\Cms\Domain\Services\PageBlockService;
use Modules\Cms\Domain\ValueObjects\PageBlockId;

class DuplicateBlockHandler implements CommandHandler
{
    public function __construct(
        private readonly PageBlockService $pageBlockService,
    ) {
    }

    /**
     * Handle the command to duplicate a block.
     *
     * @param Command|DuplicateBlockCommand $command
     * @return PageBlock
     * @throws PageBlockNotFoundException|\App\Exceptions\AuthorizationException
     */
    public function handle(Command $command): PageBlock
    {
        /** @var DuplicateBlockCommand $command */
        $blockId = new PageBlockId($command->pageBlockId);

        /** @var \Modules\User\Infrastructure\Models\User $user */
        $user = Auth::user();

        // Get Eloquent model for policy check
        $blockModel = \Modules\Cms\Infrastructure\Models\PageBlock::find($command->pageBlockId);
        if ($blockModel) {
            $user->can('duplicate', $blockModel);
        }

        return $this->pageBlockService->duplicateBlock($blockId);
    }
}
