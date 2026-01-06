<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Services;

use Modules\Cms\Domain\Entities\PageBlock;
use Modules\Cms\Domain\Repositories\PageBlockRepositoryInterface;
use Modules\Cms\Domain\ValueObjects\PageBlockId;

/**
 * PageBlock Domain Service
 * 
 * Business logic for page block operations
 */
class PageBlockService
{
    public function __construct(
        private readonly PageBlockRepositoryInterface $repository
    ) {
    }

    /**
     * Add a new block to a page
     */
    public function addBlockToPage(int $pageId, string $componentClass): PageBlock
    {
        $currentCount = $this->repository->countByPageId($pageId);
        $newId = $this->repository->nextId();

        $block = PageBlock::create(
            $newId,
            $pageId,
            $componentClass,
            $currentCount
        );

        $this->repository->save($block);

        return $block;
    }

    /**
     * Update block content
     */
    public function updateBlockContent(PageBlockId $blockId, array $content): array
    {
        $block = $this->repository->findById($blockId);
        $oldContent = $block->getContent();

        $block->updateContent($content);
        $this->repository->save($block);

        return $oldContent;
    }

    /**
     * Delete block and reorder remaining blocks
     */
    public function deleteBlock(PageBlockId $blockId): PageBlock
    {
        $block = $this->repository->findById($blockId);
        $pageId = $block->getPageId();
        $order = $block->getOrder();

        $this->repository->delete($blockId);
        $this->repository->decrementOrdersAfter($pageId, $order);

        return $block;
    }

    /**
     * Duplicate block
     */
    public function duplicateBlock(PageBlockId $blockId): PageBlock
    {
        $original = $this->repository->findById($blockId);
        $newId = $this->repository->nextId();

        $duplicate = $original->duplicate($newId);

        // Increment orders after the original block
        $this->repository->incrementOrdersAfter(
            $duplicate->getPageId(),
            $duplicate->getOrder()
        );

        $this->repository->save($duplicate);

        return $duplicate;
    }

    /**
     * Update block order
     */
    public function updateBlockOrder(int $pageId, array $orderedIds): void
    {
        $orderMap = [];
        foreach ($orderedIds as $index => $id) {
            $orderMap[$id] = $index;
        }

        $this->repository->updateOrders($orderMap);
    }

    /**
     * Restore deleted block
     */
    public function restoreBlock(
        int $pageId,
        string $componentClass,
        int $order,
        array $content = []
    ): PageBlock {
        $newId = $this->repository->nextId();

        // Make room for the restored block
        $this->repository->incrementOrdersAfter($pageId, $order);

        $block = PageBlock::create($newId, $pageId, $componentClass, $order);
        $block->updateContent($content);

        $this->repository->save($block);

        return $block;
    }
}

