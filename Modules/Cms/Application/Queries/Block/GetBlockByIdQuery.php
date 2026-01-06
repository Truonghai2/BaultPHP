<?php

namespace Modules\Cms\Application\Queries\Block;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetBlockByIdQuery
 *
 * Query to retrieve a block by ID.
 *
 * @property-read int $blockId
 */
class GetBlockByIdQuery implements QueryInterface
{
    public function __construct(
        public readonly int $blockId,
    ) {
    }

    public function getQueryName(): string
    {
        return 'cms.block.get_by_id';
    }
}
