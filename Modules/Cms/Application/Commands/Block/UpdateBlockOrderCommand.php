<?php

namespace Modules\Cms\Application\Commands\Block;

use Core\CQRS\Contracts\CommandInterface;

/**
 * UpdateBlockOrderCommand
 *
 * Command to update block display order.
 *
 * @property-read int $pageId
 * @property-read array $blockOrders
 */
class UpdateBlockOrderCommand implements CommandInterface
{
    public function __construct(
        public readonly int $pageId,
        public readonly array $blockOrders, // ['block_id' => order]
    ) {
    }

    public function getCommandName(): string
    {
        return 'cms.block.update_order';
    }
}
