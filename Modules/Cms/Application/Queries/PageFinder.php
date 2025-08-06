<?php

namespace Modules\Cms\Application\Queries;

use Modules\Cms\Domain\Exceptions\PageNotFoundException;
use Modules\Cms\Infrastructure\Models\Page;

class PageFinder
{
    /**
     * Find a page by its ID.
     *
     * @param int $id
     * @return Page
     * @throws PageNotFoundException
     */
    public function findById(int $id): Page
    {
        /** @var Page|null $page */
        $page = Page::find($id);

        if (is_null($page)) {
            throw new PageNotFoundException("Page with ID [{$id}] not found.");
        }

        return $page;
    }
}
