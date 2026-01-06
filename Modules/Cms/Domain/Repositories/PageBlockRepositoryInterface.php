<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Repositories;

use Modules\Cms\Domain\Entities\PageBlock;
use Modules\Cms\Domain\ValueObjects\PageBlockId;

/**
 * PageBlock Repository Interface
 */
interface PageBlockRepositoryInterface
{
    /**
     * Find page block by ID
     *
     * @throws \Modules\Cms\Domain\Exceptions\PageBlockNotFoundException
     */
    public function findById(PageBlockId $id): PageBlock;

    /**
     * Find page block by ID or return null
     */
    public function findByIdOrNull(PageBlockId $id): ?PageBlock;

    /**
     * Find all blocks for a page
     *
     * @return PageBlock[]
     */
    public function findByPageId(int $pageId): array;

    /**
     * Count blocks for a page
     */
    public function countByPageId(int $pageId): int;

    /**
     * Save page block
     */
    public function save(PageBlock $block): void;

    /**
     * Delete page block
     */
    public function delete(PageBlockId $id): void;

    /**
     * Get next available ID
     */
    public function nextId(): PageBlockId;

    /**
     * Update order for blocks greater than given order
     */
    public function incrementOrdersAfter(int $pageId, int $order): void;

    /**
     * Update order for blocks greater than or equal to given order
     */
    public function decrementOrdersAfter(int $pageId, int $order): void;

    /**
     * Update order for multiple blocks at once
     *
     * @param array<int, int> $orderMap [block_id => order]
     */
    public function updateOrders(array $orderMap): void;
}
