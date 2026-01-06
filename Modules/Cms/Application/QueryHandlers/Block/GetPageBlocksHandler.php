<?php

namespace Modules\Cms\Application\QueryHandlers\Block;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\Cms\Application\Queries\Block\GetPageBlocksQuery;
use Modules\Cms\Infrastructure\Models\PageBlock;

/**
 * GetPageBlocksHandler
 *
 * Handles GetPageBlocksQuery.
 */
class GetPageBlocksHandler implements QueryHandlerInterface
{
    public function handle(GetPageBlocksQuery $query): array
    {
        $blockQuery = PageBlock::where('page_id', '=', $query->pageId);

        // Apply region filter
        if ($query->region) {
            $blockQuery->where('region', '=', $query->region);
        }

        // Apply visibility filter
        if ($query->visibleOnly) {
            $blockQuery->where('visible', '=', true);
        }

        $blocks = $blockQuery->orderBy('sort_order', 'asc')->get();

        return $blocks->map(function ($block) {
            return [
                'id' => $block->id,
                'page_id' => $block->page_id,
                'block_type_id' => $block->block_type_id,
                'region' => $block->region,
                'content' => $block->content,
                'sort_order' => $block->sort_order,
                'visible' => $block->visible,
                'created_at' => $block->created_at,
                'updated_at' => $block->updated_at,
            ];
        })->toArray();
    }
}
