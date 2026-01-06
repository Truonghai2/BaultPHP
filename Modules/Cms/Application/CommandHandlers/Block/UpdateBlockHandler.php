<?php

namespace Modules\Cms\Application\CommandHandlers\Block;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\Cms\Infrastructure\Models\PageBlock;

/**
 * UpdateBlockHandler
 *
 * Handles the UpdateBlockCommand.
 */
class UpdateBlockHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): bool
    {
        $block = PageBlock::find($command->blockId);

        if (!$block) {
            throw new \Exception("Block with ID {$command->blockId} not found");
        }

        // Update fields
        if ($command->content !== null) {
            $block->content = $command->content;
        }

        if ($command->visible !== null) {
            $block->visible = $command->visible;
        }

        if ($command->sortOrder !== null) {
            $block->sort_order = $command->sortOrder;
        }

        $block->save();

        // Audit log (update is auto-logged)
        Audit::log(
            'data_change',
            'Block updated',
            [
                'block_id' => $block->id,
                'page_id' => $block->page_id,
                'action' => 'block_updated',
            ],
            'info',
        );

        return true;
    }
}
