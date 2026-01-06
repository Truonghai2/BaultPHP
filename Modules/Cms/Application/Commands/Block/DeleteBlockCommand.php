<?php

namespace Modules\Cms\Application\Commands\Block;

use Core\CQRS\Contracts\CommandInterface;

/**
 * DeleteBlockCommand
 * 
 * Command to delete a block from a page.
 */
class DeleteBlockCommand implements CommandInterface
{
    public function __construct(
        public readonly int $blockId
    ) {}

    public function getCommandName(): string
    {
        return 'cms.block.delete';
    }
}

