<?php

namespace Modules\Cms\Application\QueryHandlers\Page;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\Cms\Application\Queries\Page\GetPagesQuery;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * GetPagesHandler
 *
 * Handles GetPagesQuery.
 */
class GetPagesHandler implements QueryHandlerInterface
{
    public function handle(GetPagesQuery $query): array
    {
        $pageQuery = Page::query();

        // Apply status filter
        if ($query->status) {
            $pageQuery->where('status', '=', $query->status);
        }

        // Apply limit and offset
        if ($query->limit) {
            $pageQuery->limit($query->limit);
        }

        if ($query->offset) {
            $pageQuery->offset($query->offset);
        }

        $pages = $pageQuery->orderBy('created_at', 'desc')->get();

        // Map to array
        return $pages->map(function ($page) use ($query) {
            $pageData = $page->getAttributes();

            if ($query->withBlocks) {
                $blocks = $page->blocks()->get();
                $pageData['blocks'] = $blocks->map(function ($block) {
                    return [
                        'id' => $block->id,
                        'block_type_id' => $block->block_type_id,
                        'region' => $block->region,
                        'content' => $block->content,
                        'sort_order' => $block->sort_order,
                        'visible' => $block->visible,
                    ];
                })->toArray();
            }

            return $pageData;
        })->toArray();
    }
}
