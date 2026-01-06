<?php

namespace Modules\Cms\Application\Queries\Block;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetPageBlocksQuery
 * 
 * Query to retrieve all blocks for a page.
 */
class GetPageBlocksQuery implements QueryInterface
{
    public function __construct(
        public readonly int $pageId,
        public readonly ?string $region = null,
        public readonly bool $visibleOnly = false
    ) {}

    public function getQueryName(): string
    {
        return 'cms.block.get_page_blocks';
    }
}

