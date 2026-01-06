<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Services;

use Modules\Cms\Domain\Entities\Page;
use Modules\Cms\Domain\Repositories\PageRepositoryInterface;
use Modules\Cms\Domain\ValueObjects\PageContent;
use Modules\Cms\Domain\ValueObjects\Slug;

/**
 * Page Domain Service
 * 
 * Contains business logic related to pages
 */
class PageService
{
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository
    ) {
    }

    /**
     * Update page content
     */
    public function updateContent(
        Page $page,
        PageContent $content,
        ?string $featuredImagePath = null
    ): Page {
        // Business logic: validate content
        $this->validateContent($content);

        // Update page
        $page->updateContent($content);

        if ($featuredImagePath !== null) {
            $page->updateFeaturedImage($featuredImagePath);
        }

        return $page;
    }

    /**
     * Rename page with slug uniqueness check
     */
    public function rename(Page $page, string $newName, string $newSlug): Page
    {
        $slug = new Slug($newSlug);

        // Business rule: slug must be unique
        if ($this->pageRepository->slugExists($slug, $page->getId())) {
            throw new \DomainException("Slug '{$newSlug}' is already in use");
        }

        $page->rename($newName, $slug);

        return $page;
    }

    /**
     * Validate page content
     */
    private function validateContent(PageContent $content): void
    {
        // Business rules for content validation
        if ($content->count() > 100) {
            throw new \DomainException('Page cannot have more than 100 blocks');
        }

        // More validation rules...
    }

    /**
     * Check if page can be deleted
     */
    public function canDelete(Page $page): bool
    {
        // Business rules for deletion
        // e.g., check if page has dependencies, is published, etc.
        return true;
    }
}

