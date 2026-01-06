<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Services;

use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\Cms\Infrastructure\Models\BlockRegion;
use Modules\Cms\Infrastructure\Models\Page;
use Psr\Log\LoggerInterface;

/**
 * Block Duplication Service
 *
 * Allows duplicating blocks across multiple pages or converting page-specific blocks to global blocks
 */
class BlockDuplicationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Duplicate a block to other pages
     *
     * @param BlockInstance $sourceBlock The block to duplicate
     * @param array<int> $targetPageIds IDs of pages to duplicate to
     * @param bool $keepOriginalRegion Whether to use the same region name
     * @return array Statistics about the duplication
     */
    public function duplicateToPages(BlockInstance $sourceBlock, array $targetPageIds, bool $keepOriginalRegion = true): array
    {
        $stats = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($targetPageIds as $pageId) {
            try {
                $targetPage = Page::find($pageId);

                if (!$targetPage) {
                    $stats['failed']++;
                    $stats['errors'][] = "Page {$pageId} not found";
                    continue;
                }

                // Determine target region
                if ($keepOriginalRegion && $sourceBlock->region) {
                    // Extract region type from source region name (e.g., 'hero', 'content', 'sidebar')
                    $regionType = $this->extractRegionType($sourceBlock->region->name);
                    $targetRegionName = "page-{$targetPage->slug}-{$regionType}";
                } else {
                    $targetRegionName = "page-{$targetPage->slug}-content";
                }

                $targetRegion = BlockRegion::where('name', $targetRegionName)->first();

                if (!$targetRegion) {
                    // Create region if it doesn't exist
                    $targetRegion = BlockRegion::create([
                        'name' => $targetRegionName,
                        'title' => ucfirst(str_replace('-', ' ', $targetRegionName)),
                        'description' => "Auto-created region for {$targetPage->name}",
                        'max_blocks' => 10,
                        'is_active' => true,
                    ]);
                }

                // Get max weight in target region
                $maxWeight = BlockInstance::where('region_id', $targetRegion->id)
                    ->where('context_type', 'page')
                    ->where('context_id', $pageId)
                    ->max('weight') ?? -1;

                // Create duplicate block
                $duplicatedBlock = new BlockInstance();
                $duplicatedBlock->block_type_id = $sourceBlock->block_type_id;
                $duplicatedBlock->region_id = $targetRegion->id;
                $duplicatedBlock->context_type = 'page';
                $duplicatedBlock->context_id = $pageId;
                $duplicatedBlock->title = $sourceBlock->title;
                $duplicatedBlock->config = $sourceBlock->config;
                $duplicatedBlock->content = $sourceBlock->content;
                $duplicatedBlock->weight = $maxWeight + 1;
                $duplicatedBlock->visible = $sourceBlock->visible;
                $duplicatedBlock->visibility_mode = $sourceBlock->visibility_mode;
                $duplicatedBlock->visibility_rules = $sourceBlock->visibility_rules;
                $duplicatedBlock->allowed_roles = $sourceBlock->allowed_roles;
                $duplicatedBlock->denied_roles = $sourceBlock->denied_roles;
                $duplicatedBlock->created_by = $sourceBlock->created_by;
                $duplicatedBlock->save();

                $stats['success']++;

                $this->logger->info("Duplicated block {$sourceBlock->id} to page {$pageId}", [
                    'source_block_id' => $sourceBlock->id,
                    'target_page_id' => $pageId,
                    'new_block_id' => $duplicatedBlock->id,
                ]);

            } catch (\Throwable $e) {
                $stats['failed']++;
                $stats['errors'][] = "Page {$pageId}: {$e->getMessage()}";

                $this->logger->error("Failed to duplicate block to page {$pageId}", [
                    'source_block_id' => $sourceBlock->id,
                    'target_page_id' => $pageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Convert a page-specific block to a global block
     *
     * This makes the block appear on all pages in the same region
     *
     * @param BlockInstance $block The block to convert
     * @param string $targetRegionName Global region name (e.g., 'header', 'content', 'sidebar')
     * @return bool Success status
     */
    public function convertToGlobal(BlockInstance $block, string $targetRegionName): bool
    {
        try {
            // Find or create global region
            $globalRegion = BlockRegion::firstOrCreate(
                ['name' => $targetRegionName],
                [
                    'title' => ucfirst($targetRegionName),
                    'description' => "Global {$targetRegionName} region",
                    'max_blocks' => 20,
                    'is_active' => true,
                ],
            );

            // Get max weight in global region
            $maxWeight = BlockInstance::where('region_id', $globalRegion->id)
                ->where('context_type', 'global')
                ->max('weight') ?? -1;

            // Update block to global context
            $block->context_type = 'global';
            $block->context_id = null;
            $block->region_id = $globalRegion->id;
            $block->weight = $maxWeight + 1;
            $block->save();

            $this->logger->info("Converted block {$block->id} to global", [
                'block_id' => $block->id,
                'region' => $targetRegionName,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to convert block to global', [
                'block_id' => $block->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Duplicate all blocks from one page to another
     *
     * @param int $sourcePageId Source page ID
     * @param int $targetPageId Target page ID
     * @param bool $includeHiddenBlocks Whether to duplicate hidden blocks
     * @return array Statistics about the duplication
     */
    public function duplicateAllBlocksToPage(int $sourcePageId, int $targetPageId, bool $includeHiddenBlocks = false): array
    {
        $stats = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $sourcePage = Page::find($sourcePageId);
        $targetPage = Page::find($targetPageId);

        if (!$sourcePage || !$targetPage) {
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => ['Source or target page not found'],
            ];
        }

        // Get all blocks from source page
        $query = BlockInstance::where('context_type', 'page')
            ->where('context_id', $sourcePageId);

        if (!$includeHiddenBlocks) {
            $query->where('visible', true);
        }

        $sourceBlocks = $query->get();

        foreach ($sourceBlocks as $sourceBlock) {
            try {
                $result = $this->duplicateToPages($sourceBlock, [$targetPageId], true);
                $stats['success'] += $result['success'];
                $stats['failed'] += $result['failed'];
                $stats['errors'] = array_merge($stats['errors'], $result['errors']);
            } catch (\Throwable $e) {
                $stats['failed']++;
                $stats['errors'][] = "Block {$sourceBlock->id}: {$e->getMessage()}";
            }
        }

        $this->logger->info("Duplicated all blocks from page {$sourcePageId} to {$targetPageId}", $stats);

        return $stats;
    }

    /**
     * Sync block across all pages
     *
     * Updates all instances of a block type across all pages
     * Useful for making mass changes to similar blocks
     *
     * @param int $blockTypeId Block type to sync
     * @param array $config New configuration to apply
     * @return int Number of blocks updated
     */
    public function syncBlockTypeAcrossPages(int $blockTypeId, array $config): int
    {
        $updated = BlockInstance::where('block_type_id', $blockTypeId)
            ->where('context_type', 'page')
            ->update(['config' => $config]);

        $this->logger->info("Synced block type {$blockTypeId} across {$updated} pages");

        return $updated;
    }

    /**
     * Extract region type from full region name
     * e.g., 'page-home-hero' -> 'hero'
     */
    private function extractRegionType(string $regionName): string
    {
        $parts = explode('-', $regionName);

        // If it's a page-specific region (page-slug-type), return the last part
        if (count($parts) >= 3 && $parts[0] === 'page') {
            return end($parts);
        }

        // Otherwise return as-is
        return $regionName;
    }
}
