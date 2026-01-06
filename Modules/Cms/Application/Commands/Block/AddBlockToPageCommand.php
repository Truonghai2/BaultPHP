<?php

namespace Modules\Cms\Application\Commands\Block;

use Core\CQRS\Contracts\CommandInterface;

/**
 * AddBlockToPageCommand
 *
 * Command to add a block to a page.
 *
 * @property-read int $pageId
 * @property-read int $blockTypeId
 * @property-read string $region
 * @property-read array|null $config
 * @property-read string|null $content
 * @property-read int $sortOrder
 */
class AddBlockToPageCommand implements CommandInterface
{
    public function __construct(
        public readonly int $pageId,
        public readonly int $blockTypeId,
        public readonly string $region,
        public readonly ?array $config = null,
        public readonly ?string $content = null,
        public readonly int $sortOrder = 0,
    ) {
    }

    public function getCommandName(): string
    {
        return 'cms.block.add_to_page';
    }
}
