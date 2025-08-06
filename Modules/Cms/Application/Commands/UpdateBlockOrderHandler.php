<?php

namespace Modules\Cms\Application\Commands;

use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Support\Facades\Auth;
use Modules\Cms\Infrastructure\Models\PageBlock;

class UpdateBlockOrderHandler implements CommandHandler
{
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

        foreach ($command->orderedIds as $index => $id) {
            PageBlock::where('id', '=', $id)
                ->where('page_id', '=', $command->page->id)
                ->update(['order' => $index]);
        }
    }
}
