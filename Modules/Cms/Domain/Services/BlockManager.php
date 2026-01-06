<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Services;

use Modules\Cms\Domain\Entities\BlockInstance;
use Modules\Cms\Domain\Repositories\BlockInstanceRepositoryInterface;
use Modules\Cms\Domain\Repositories\BlockRegionRepositoryInterface;
use Modules\Cms\Domain\ValueObjects\BlockConfiguration;
use Modules\Cms\Domain\ValueObjects\BlockId;
use Modules\Cms\Domain\ValueObjects\RegionName;
use Modules\Cms\Infrastructure\Models\BlockType;
use Psr\Log\LoggerInterface;

/**
 * Block Manager Domain Service
 *
 * Business logic for block management
 */
class BlockManager
{
    public function __construct(
        private readonly BlockInstanceRepositoryInterface $blockInstanceRepository,
        private readonly BlockRegionRepositoryInterface $blockRegionRepository,
        private readonly BlockRegistry $registry,
        private readonly BlockRenderer $renderer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Create a new block instance
     */
    public function createBlock(
        string $blockTypeName,
        string $regionName,
        array $attributes = [],
    ): BlockInstance {
        $blockType = BlockType::where('name', $blockTypeName)->firstOrFail();
        $region = $this->blockRegionRepository->findByName(new RegionName($regionName));

        $currentCount = $this->blockInstanceRepository->countInRegion($region->getId());
        if (!$region->canAddBlock($currentCount)) {
            throw new \DomainException("Region {$regionName} is full (max: {$region->getMaxBlocks()})");
        }

        $maxWeight = $this->blockInstanceRepository->getMaxWeightInRegion($region->getId());

        $blockId = $this->blockInstanceRepository->nextId();
        $instance = new BlockInstance(
            $blockId,
            $blockType->id,
            $region->getId(),
            $attributes['context_type'] ?? 'global',
            $attributes['context_id'] ?? null,
            $attributes['title'] ?? $blockType->title,
            isset($attributes['config']) ? BlockConfiguration::fromArray($attributes['config']) : BlockConfiguration::fromArray($blockType->default_config ?? []),
            $attributes['content'] ?? null,
            $maxWeight + 1,
            $attributes['visible'] ?? true,
            $attributes['visibility_mode'] ?? 'show',
            $attributes['visibility_rules'] ?? null,
            $attributes['allowed_roles'] ?? null,
            $attributes['denied_roles'] ?? null,
            $attributes['created_by'] ?? null,
        );

        $block = $this->registry->getBlock($blockTypeName);
        if ($block) {
            $block->beforeSave($instance);
        }

        $this->blockInstanceRepository->save($instance);

        if ($block) {
            $block->afterSave($instance);
        }

        $this->logger->info('Block instance created', [
            'id' => $instance->getId()->getValue(),
            'type' => $blockTypeName,
            'region' => $regionName,
        ]);

        return $instance;
    }

    /**
     * Update block instance
     */
    public function updateBlock(BlockInstance $instance, array $attributes): BlockInstance
    {
        if (isset($attributes['title'])) {
            $instance->updateTitle($attributes['title']);
        }

        if (isset($attributes['content'])) {
            $instance->updateContent($attributes['content']);
        }

        if (isset($attributes['config'])) {
            $config = BlockConfiguration::fromArray($attributes['config']);
            $instance->updateConfiguration($config);
        }

        if (isset($attributes['visible'])) {
            $attributes['visible'] ? $instance->show() : $instance->hide();
        }

        $this->blockInstanceRepository->save($instance);
        $this->renderer->clearBlockCache($instance);

        $this->logger->info('Block instance updated', [
            'id' => $instance->getId()->getValue(),
        ]);

        return $instance;
    }

    /**
     * Delete block instance
     */
    public function deleteBlock(BlockInstance $instance): void
    {
        $id = $instance->getId();
        $this->blockInstanceRepository->delete($id);
        $this->renderer->clearBlockCache($instance);

        $this->logger->info('Block instance deleted', [
            'id' => $id->getValue(),
        ]);
    }

    /**
     * Move block up (decrease weight)
     */
    public function moveUp(BlockInstance $instance): void
    {
        $instance->moveUp();
        $this->blockInstanceRepository->save($instance);

        $this->logger->debug('Block moved up', ['id' => $instance->getId()->getValue()]);
    }

    /**
     * Move block down (increase weight)
     */
    public function moveDown(BlockInstance $instance): void
    {
        $instance->moveDown();
        $this->blockInstanceRepository->save($instance);

        $this->logger->debug('Block moved down', ['id' => $instance->getId()->getValue()]);
    }

    /**
     * Move block to different region
     */
    public function moveToRegion(BlockInstance $instance, string $targetRegionName): void
    {
        $targetRegion = $this->blockRegionRepository->findByName(new RegionName($targetRegionName));

        $currentCount = $this->blockInstanceRepository->countInRegion($targetRegion->getId());
        if (!$targetRegion->canAddBlock($currentCount)) {
            throw new \DomainException("Target region {$targetRegionName} is full");
        }

        $maxWeight = $this->blockInstanceRepository->getMaxWeightInRegion($targetRegion->getId());

        $instance->moveToRegion($targetRegion->getId());
        $instance->setWeight($maxWeight + 1);
        $this->blockInstanceRepository->save($instance);

        $this->logger->info('Block moved to region', [
            'id' => $instance->getId()->getValue(),
            'region' => $targetRegionName,
        ]);
    }

    /**
     * Toggle block visibility
     */
    public function toggleVisibility(BlockInstance $instance): void
    {
        $instance->toggleVisibility();
        $this->blockInstanceRepository->save($instance);
        $this->renderer->clearBlockCache($instance);

        $this->logger->debug('Block visibility toggled', [
            'id' => $instance->getId()->getValue(),
            'visible' => $instance->isVisible(),
        ]);
    }

    /**
     * Duplicate block instance
     */
    public function duplicateBlock(BlockInstance $instance): BlockInstance
    {
        $newId = $this->blockInstanceRepository->nextId();
        $newInstance = new BlockInstance(
            $newId,
            $instance->getBlockTypeId(),
            $instance->getRegionId(),
            $instance->getContextType(),
            $instance->getContextId(),
            $instance->getTitle() . ' (Copy)',
            $instance->getConfig(),
            $instance->getContent(),
            $instance->getWeight() + 1,
            $instance->isVisible(),
        );

        $this->blockInstanceRepository->save($newInstance);

        $this->logger->info('Block duplicated', [
            'original_id' => $instance->getId()->getValue(),
            'new_id' => $newInstance->getId()->getValue(),
        ]);

        return $newInstance;
    }

    /**
     * Get blocks for a region
     */
    public function getBlocksForRegion(
        string $regionName,
        ?string $contextType = 'global',
        ?int $contextId = null,
    ): array {
        $region = $this->blockRegionRepository->findByName(new RegionName($regionName));
        return $this->blockInstanceRepository->findByRegion(
            $region->getId(),
            $contextType,
            $contextId,
        );
    }

    /**
     * Reorder blocks in a region
     */
    public function reorderBlocks(array $blockIds): void
    {
        $blockIdObjects = array_map(
            fn ($id) => $id instanceof BlockId ? $id : new BlockId((int) $id),
            $blockIds,
        );

        $this->blockInstanceRepository->reorderByIds($blockIdObjects);

        $this->logger->info('Blocks reordered', ['count' => count($blockIds)]);
    }

    /**
     * Get visible blocks for a region (for rendering)
     */
    public function getVisibleBlocksForRegion(
        string $regionName,
        ?string $contextType = 'global',
        ?int $contextId = null,
        ?array $userRoles = null,
    ): array {
        $region = $this->blockRegionRepository->findByName(new RegionName($regionName));
        $blocks = $this->blockInstanceRepository->findVisibleInRegion(
            $region->getId(),
            $contextType,
            $contextId,
        );

        if ($userRoles !== null) {
            return array_filter(
                $blocks,
                fn (BlockInstance $block) => $block->isVisibleTo($userRoles),
            );
        }

        return $blocks;
    }
}
