<?php

namespace Modules\Cms\Application\CommandHandlers\Block;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\Cms\Infrastructure\Models\PageBlock;

/**
 * DeleteBlockHandler
 * 
 * Handles the DeleteBlockCommand.
 */
class DeleteBlockHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): bool
    {
        $block = PageBlock::find($command->blockId);
        
        if (!$block) {
            throw new \Exception("Block with ID {$command->blockId} not found");
        }

        $pageId = $block->page_id;
        $blockTypeId = $block->block_type_id;

        // Delete block
        $block->delete();

        // Audit log (deletion is auto-logged)
        Audit::log(
            'data_change',
            "Block deleted from page",
            [
                'block_id' => $command->blockId,
                'page_id' => $pageId,
                'block_type_id' => $blockTypeId,
                'action' => 'block_deleted'
            ],
            'warning'
        );

        return true;
    }
}

