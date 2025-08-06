<?php

namespace Modules\Cms\Application\Commands;

use App\Exceptions\AuthorizationException;
use Core\CQRS\Command;
use Core\CQRS\CommandHandler;
use Core\Support\Facades\Auth;
use Modules\Cms\Infrastructure\Models\PageBlock;

class AddBlockToPageHandler implements CommandHandler
{
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

        // Logic phân quyền được đặt ở đây là hợp lý nhất vì nó cần context của $page.
        $user->can('update', $command->page);

        $currentBlockCount = PageBlock::where('page_id', '=', $command->page->id)->count();

        $newBlock = PageBlock::create([
            'page_id' => $command->page->id,
            'component_class' => $command->componentClass,
            'order' => $currentBlockCount,
        ]);

        if (!$newBlock) {
            // This should ideally not happen, but it's good practice to handle the failure case.
            throw new \RuntimeException("Failed to create a new page block for page ID: {$command->page->id}.");
        }

        return $newBlock;
    }
}
