<?php

namespace Modules\Cms\Application\Queries;

use Core\Support\Collection;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;

class PageBlockFinder
{
    /**
     * Find all blocks for a given page, ordered correctly.
     *
     * @param Page $page
     * @return Collection<int, PageBlock>
     */
    public function findByPage(Page $page): Collection
    {
        // The `blocks` relationship on the Page model is already defined
        // to fetch the blocks in the correct order. Using the relationship
        // is cleaner, more idiomatic, and leverages the ORM's capabilities.
        return $page->blocks;
    }
}
