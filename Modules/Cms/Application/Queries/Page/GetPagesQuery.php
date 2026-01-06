<?php

namespace Modules\Cms\Application\Queries\Page;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetPagesQuery
 *
 * Query to retrieve a list of pages with filters.
 */
class GetPagesQuery implements QueryInterface
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?int $limit = null,
        public readonly ?int $offset = null,
        public readonly bool $withBlocks = false,
    ) {
    }

    public function getQueryName(): string
    {
        return 'cms.page.get_all';
    }
}
