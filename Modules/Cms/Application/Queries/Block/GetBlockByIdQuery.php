<?php

namespace Modules\Cms\Application\Queries\Block;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetBlockByIdQuery
 * 
 * Query to retrieve a block by ID.
 */
class GetBlockByIdQuery implements QueryInterface
{
    public function __construct(
        public readonly int $blockId
    ) {}

    public function getQueryName(): string
    {
        return 'cms.block.get_by_id';
    }
}

