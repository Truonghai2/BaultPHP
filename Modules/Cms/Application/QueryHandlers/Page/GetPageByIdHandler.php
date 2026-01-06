<?php

namespace Modules\Cms\Application\QueryHandlers\Page;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\Cms\Application\Queries\Page\GetPageByIdQuery;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * GetPageByIdHandler
 * 
 * Handles GetPageByIdQuery.
 */
class GetPageByIdHandler implements QueryHandlerInterface
{
    public function handle(GetPageByIdQuery $query): ?array
    {
        $page = Page::find($query->pageId);
        
        if (!$page) {
            return null;
        }

        $pageData = $page->getAttributes();

        if ($query->withBlocks) {
            $blocks = $page->blocks()->get();
            $pageData['blocks'] = $blocks->map(function($block) {
                return [
                    'id' => $block->id,
                    'block_type_id' => $block->block_type_id,
                    'region' => $block->region,
                    'content' => $block->content,
                    'sort_order' => $block->sort_order,
                    'visible' => $block->visible
                ];
            })->toArray();
        }

        return $pageData;
    }
}

