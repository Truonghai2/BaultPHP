<?php

namespace Modules\Cms\Application\Queries\Page;

use Core\CQRS\Contracts\QueryInterface;

/**
 * GetPageBySlugQuery
 *
 * Query to retrieve a page by slug.
 *
 * @property-read string $slug
 * @property-read bool $withBlocks
 * @property-read bool $publishedOnly
 */
class GetPageBySlugQuery implements QueryInterface
{
    public function __construct(
        public readonly string $slug,
        public readonly bool $withBlocks = false,
        public readonly bool $publishedOnly = true,
    ) {
    }

    public function getQueryName(): string
    {
        return 'cms.page.get_by_slug';
    }
}
