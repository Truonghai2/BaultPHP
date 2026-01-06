<?php

namespace Modules\Cms\Application\CommandHandlers\Block;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\Cms\Infrastructure\Models\BlockType;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;

/**
 * AddBlockToPageHandler
 * 
 * Handles the AddBlockToPageCommand.
 */
class AddBlockToPageHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): int
    {
        // Validate page exists
        $page = Page::find($command->pageId);
        if (!$page) {
            throw new \Exception("Page with ID {$command->pageId} not found");
        }

        // Validate block type exists
        $blockType = BlockType::find($command->blockTypeId);
        if (!$blockType) {
            throw new \Exception("Block type with ID {$command->blockTypeId} not found");
        }

        // Create block
        $block = PageBlock::create([
            'page_id' => $command->pageId,
            'block_type_id' => $command->blockTypeId,
            'region' => $command->region,
            'content' => $command->content,
            'sort_order' => $command->sortOrder,
            'visible' => true,
            'created_by' => auth()->id()
        ]);

        // Audit log (creation is auto-logged)
        Audit::log(
            'data_change',
            "Block added to page: {$page->name}",
            [
                'page_id' => $command->pageId,
                'block_id' => $block->id,
                'block_type' => $blockType->name,
                'region' => $command->region,
                'action' => 'block_added'
            ],
            'info'
        );

        return $block->id;
    }
}

