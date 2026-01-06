<?php

namespace Modules\Cms\Application\CommandHandlers\Block;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\Support\Facades\Audit;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;

/**
 * UpdateBlockOrderHandler  
 * 
 * Handles the UpdateBlockOrderCommand.
 */
class UpdateBlockOrderHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): bool
    {
        // Validate page exists
        $page = Page::find($command->pageId);
        if (!$page) {
            throw new \Exception("Page with ID {$command->pageId} not found");
        }

        // Update block orders
        foreach ($command->blockOrders as $blockId => $order) {
            $block = PageBlock::where('id', '=', $blockId)
                ->where('page_id', '=', $command->pageId)
                ->first();
                
            if ($block) {
                $block->sort_order = $order;
                $block->save();
            }
        }

        // Audit log
        Audit::log(
            'data_change',
            "Block order updated for page: {$page->name}",
            [
                'page_id' => $command->pageId,
                'block_orders' => $command->blockOrders,
                'action' => 'block_order_updated'
            ],
            'info'
        );

        return true;
    }
}

