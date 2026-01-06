<?php

namespace Modules\Cms\Application\Queries\Page;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetPageByIdQuery
 *
 * Query to retrieve a page by ID.
 *
 * @property-read int $pageId
 * @property-read bool $withBlocks
 */
class GetPageByIdQuery implements QueryInterface
{
    public function __construct(
        public readonly int $pageId,
        public readonly bool $withBlocks = false,
    ) {
    }

    public function getQueryName(): string
    {
        return 'cms.page.get_by_id';
    }
}
