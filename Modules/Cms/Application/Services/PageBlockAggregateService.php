<?php

declare(strict_types=1);

namespace Modules\Cms\Application\Services;

use Core\EventSourcing\AggregateRepository;
use Core\Support\Facades\Audit;
use Modules\Cms\Domain\Aggregates\PageBlockAggregate;
use Ramsey\Uuid\Uuid;

/**
 * Page Block Aggregate Service
 *
 * Application service for page block operations
 */
class PageBlockAggregateService
{
    public function __construct(
        private AggregateRepository $aggregateRepository,
    ) {
    }

    /**
     * Create a new block (Event Sourced)
     */
    public function createBlock(
        string $pageId,
        string $componentClass,
        int $sortOrder,
        string $userId,
    ): string {
        $blockId = Uuid::uuid4()->toString();

        $block = new PageBlockAggregate();
        $block->create($blockId, $pageId, $componentClass, $sortOrder, $userId);

        $this->aggregateRepository->save($block);

        Audit::log(
            'cms_action',
            'Block created via Event Sourcing',
            [
                'block_id' => $blockId,
                'page_id' => $pageId,
                'component_class' => $componentClass,
                'user_id' => $userId,
                'method' => 'event_sourcing',
            ],
            'info',
        );

        return $blockId;
    }

    /**
     * Update block content (Event Sourced)
     */
    public function updateBlockContent(
        string $blockId,
        array $content,
        string $userId,
    ): void {
        $block = $this->loadBlock($blockId);

        $block->updateContent($content, $userId);

        $this->aggregateRepository->save($block);

        Audit::log(
            'cms_action',
            'Block content updated',
            [
                'block_id' => $blockId,
                'user_id' => $userId,
                'content_size' => strlen(json_encode($content)),
            ],
            'info',
        );
    }

    /**
     * Change block order
     */
    public function changeBlockOrder(
        string $blockId,
        int $newOrder,
        string $userId,
    ): void {
        $block = $this->loadBlock($blockId);

        $block->changeOrder($newOrder, $userId);

        $this->aggregateRepository->save($block);

        Audit::log(
            'cms_action',
            'Block order changed',
            [
                'block_id' => $blockId,
                'new_order' => $newOrder,
                'user_id' => $userId,
            ],
            'info',
        );
    }

    /**
     * Duplicate block
     */
    public function duplicateBlock(
        string $blockId,
        int $newOrder,
        string $userId,
    ): string {
        $block = $this->loadBlock($blockId);

        $newBlockId = Uuid::uuid4()->toString();

        // Record duplication on original block
        $block->duplicate($newBlockId, $newOrder, $userId);
        $this->aggregateRepository->save($block);

        // Create the new block aggregate
        $newBlock = new PageBlockAggregate();
        $newBlock->create(
            $newBlockId,
            $block->getPageId(),
            $block->getComponentClass(),
            $newOrder,
            $userId,
        );

        // Copy content
        if (!empty($block->getContent())) {
            $newBlock->updateContent($block->getContent(), $userId);
        }

        $this->aggregateRepository->save($newBlock);

        Audit::log(
            'cms_action',
            'Block duplicated',
            [
                'original_block_id' => $blockId,
                'new_block_id' => $newBlockId,
                'user_id' => $userId,
            ],
            'info',
        );

        return $newBlockId;
    }

    /**
     * Delete block (soft delete)
     */
    public function deleteBlock(string $blockId, string $userId): void
    {
        $block = $this->loadBlock($blockId);

        $block->delete($userId);

        $this->aggregateRepository->save($block);

        Audit::log(
            'cms_action',
            'Block deleted',
            [
                'block_id' => $blockId,
                'user_id' => $userId,
            ],
            'warning',
        );
    }

    /**
     * Restore deleted block
     */
    public function restoreBlock(string $blockId, string $userId): void
    {
        $block = $this->loadBlock($blockId);

        $block->restore($userId);

        $this->aggregateRepository->save($block);

        Audit::log(
            'cms_action',
            'Block restored',
            [
                'block_id' => $blockId,
                'user_id' => $userId,
            ],
            'info',
        );
    }

    /**
     * Get block state (as array)
     */
    public function getBlockState(string $blockId): ?array
    {
        $block = $this->getBlock($blockId);

        if (!$block) {
            return null;
        }

        return [
            'id' => $block->getId(),
            'page_id' => $block->getPageId(),
            'component_class' => $block->getComponentClass(),
            'sort_order' => $block->getSortOrder(),
            'content' => $block->getContent(),
            'is_deleted' => $block->isDeleted(),
            'deleted_at' => $block->getDeletedAt()?->format('Y-m-d H:i:s'),
            'version' => $block->getVersion(),
        ];
    }

    /**
     * Get block aggregate
     */
    public function getBlock(string $blockId): ?PageBlockAggregate
    {
        return $this->aggregateRepository->load(PageBlockAggregate::class, $blockId);
    }

    /**
     * Load block or throw exception
     */
    private function loadBlock(string $blockId): PageBlockAggregate
    {
        $block = $this->getBlock($blockId);

        if (!$block) {
            throw new \RuntimeException("Block {$blockId} not found in event store");
        }

        return $block;
    }
}
