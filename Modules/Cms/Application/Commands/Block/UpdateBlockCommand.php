<?php

namespace Modules\Cms\Application\Commands\Block;

use Core\CQRS\Contracts\CommandInterface;

/**
 * UpdateBlockCommand
 * 
 * Command to update a block's content and configuration.
 */
class UpdateBlockCommand implements CommandInterface
{
    public function __construct(
        public readonly int $blockId,
        public readonly ?string $content = null,
        public readonly ?array $config = null,
        public readonly ?bool $visible = null,
        public readonly ?int $sortOrder = null
    ) {}

    public function getCommandName(): string
    {
        return 'cms.block.update';
    }
}

