<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Repositories;

use Modules\Cms\Domain\Entities\Page;
use Modules\Cms\Domain\ValueObjects\PageId;
use Modules\Cms\Domain\ValueObjects\Slug;

/**
 * Page Repository Interface
 * 
 * Domain interface
 */
interface PageRepositoryInterface
{
    /**
     * Find page by ID
     * 
     * @throws \Modules\Cms\Domain\Exceptions\PageNotFoundException
     */
    public function findById(PageId $id): Page;

    /**
     * Find page by ID or return null
     */
    public function findByIdOrNull(PageId $id): ?Page;

    /**
     * Find page by slug
     * 
     * @throws \Modules\Cms\Domain\Exceptions\PageNotFoundException
     */
    public function findBySlug(Slug $slug): Page;

    /**
     * Check if slug exists
     */
    public function slugExists(Slug $slug, ?PageId $excludeId = null): bool;

    /**
     * Get all pages
     * 
     * @return Page[]
     */
    public function getAll(): array;

    /**
     * Get pages by user ID
     * 
     * @return Page[]
     */
    public function getByUserId(int $userId): array;

    /**
     * Save page (create or update)
     */
    public function save(Page $page): void;

    /**
     * Delete page
     */
    public function delete(PageId $id): void;

    /**
     * Get next available ID (for new pages)
     */
    public function nextId(): PageId;
}

