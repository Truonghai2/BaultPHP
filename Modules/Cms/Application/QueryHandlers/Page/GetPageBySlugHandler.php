<?php

namespace Modules\Cms\Application\QueryHandlers\Page;

use Core\CQRS\Contracts\QueryHandlerInterface;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * GetPageBySlugHandler
 *
 * Handles queries for retrieving pages by slug.
 */
class GetPageBySlugHandler implements QueryHandlerInterface
{
    public function handle($query): ?array
    {
        $pageQuery = Page::where('slug', '=', $query->slug);

        if ($query->publishedOnly) {
            $pageQuery->where('status', '=', Page::STATUS_PUBLISHED);
        }

        $page = $pageQuery->first();

        if (!$page) {
            return null;
        }

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
    }
}
