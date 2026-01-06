<?php

namespace Modules\Cms\Application\QueryHandlers\Block;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\Cms\Application\Queries\Block\GetBlockByIdQuery;
use Modules\Cms\Infrastructure\Models\PageBlock;

/**
 * GetBlockByIdHandler
 *
 * Handles GetBlockByIdQuery.
 */
class GetBlockByIdHandler implements QueryHandlerInterface
{
    public function handle(GetBlockByIdQuery $query): ?array
    {
        $block = PageBlock::find($query->blockId);

        if (!$block) {
            return null;
        }

        return [
            'id' => $block->id,
            'page_id' => $block->page_id,
            'block_type_id' => $block->block_type_id,
            'region' => $block->region,
            'content' => $block->content,
            'sort_order' => $block->sort_order,
            'visible' => $block->visible,
            'visibility_rules' => $block->visibility_rules,
            'allowed_roles' => $block->allowed_roles,
            'created_by' => $block->created_by,
            'created_at' => $block->created_at,
            'updated_at' => $block->updated_at,
        ];
    }
}
