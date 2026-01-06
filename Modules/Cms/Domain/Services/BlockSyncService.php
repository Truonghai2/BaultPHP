<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Services;

use Core\Cache\CacheManager;
use Modules\Cms\Infrastructure\Models\BlockRegion;
use Modules\Cms\Infrastructure\Models\BlockType;
use Psr\Log\LoggerInterface;

/**
 * Block Sync Service
 *
 * Automatically discovers and syncs block types and regions to database
 * without needing to restart server or run seeders
 */
class BlockSyncService
{
    private const CACHE_KEY = 'cms.blocks.last_sync';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly CacheManager $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Sync all blocks from registry to database
     *
     * @param bool $force Force sync even if recently synced
     * @return array Statistics about the sync operation
     */
    public function syncBlocks(bool $force = false): array
    {
        $stats = [
            'types_created' => 0,
            'types_updated' => 0,
            'types_deleted' => 0,
            'regions_created' => 0,
            'regions_updated' => 0,
            'total_time' => 0,
        ];

        $startTime = microtime(true);

        try {
            // Check if we need to sync
            if (!$force && !$this->needsSync()) {
                $this->logger->info('Block sync skipped - recently synced');
                return $stats;
            }

            $this->logger->info('Starting block sync...');

            // Sync block types
            $typeStats = $this->syncBlockTypes();
            $stats['types_created'] = $typeStats['created'];
            $stats['types_updated'] = $typeStats['updated'];
            $stats['types_deleted'] = $typeStats['deleted'];

            // Sync regions
            $regionStats = $this->syncBlockRegions();
            $stats['regions_created'] = $regionStats['created'];
            $stats['regions_updated'] = $regionStats['updated'];

            // Update cache
            $this->cache->put(self::CACHE_KEY, time(), self::CACHE_TTL);

            $stats['total_time'] = round(microtime(true) - $startTime, 3);

            $this->logger->info('Block sync completed', $stats);

            return $stats;
        } catch (\Throwable $e) {
            $this->logger->error('Block sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if sync is needed
     */
    private function needsSync(): bool
    {
        $lastSync = $this->cache->get(self::CACHE_KEY);

        if ($lastSync === null) {
            return true;
        }

        // In development, sync more frequently (every 30 seconds for near-realtime experience)
        if (config('app.env') === 'local') {
            return (time() - $lastSync) > 30;
        }

        // In production, use cache TTL (1 hour by default)
        return false;
    }

    /**
     * Sync block types from registry to database
     */
    private function syncBlockTypes(): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0];

        $registeredBlocks = $this->registry->getBlocks();
        $registeredNames = array_keys($registeredBlocks);

        $existingTypes = BlockType::all()->keyBy('name')->all();
        $existingNames = array_keys($existingTypes);

        foreach ($registeredBlocks as $name => $block) {
            $data = [
                'name' => $block->getName(),
                'title' => $block->getTitle(),
                'description' => $block->getDescription(),
                'category' => $block->getCategory(),
                'icon' => $block->getIcon(),
                'class' => get_class($block),
                'is_active' => true,
                'configurable' => true,
                'version' => 1,
                'default_config' => $block->getDefaultConfig(),
            ];

            if (isset($existingTypes[$name])) {
                // Update existing
                $type = $existingTypes[$name];
                $changed = false;

                foreach ($data as $key => $value) {
                    if ($type->{$key} !== $value) {
                        $type->{$key} = $value;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $type->save();
                    $stats['updated']++;
                    $this->logger->info("Updated block type: {$name}");
                }
            } else {
                // Create new
                BlockType::create($data);
                $stats['created']++;
                $this->logger->info("Created block type: {$name}");
            }
        }

        // Mark unregistered blocks as inactive (don't delete to preserve instances)
        $unregisteredNames = array_diff($existingNames, $registeredNames);
        foreach ($unregisteredNames as $name) {
            $type = $existingTypes[$name];
            if ($type->is_active) {
                $type->is_active = false;
                $type->save();
                $stats['deleted']++;
                $this->logger->warning("Deactivated unregistered block type: {$name}");
            }
        }

        return $stats;
    }

    /**
     * Sync default block regions
     */
    private function syncBlockRegions(): array
    {
        $stats = ['created' => 0, 'updated' => 0];

        $defaultRegions = $this->getDefaultRegions();

        foreach ($defaultRegions as $regionData) {
            $region = BlockRegion::where('name', $regionData['name'])->first();

            if (!$region) {
                BlockRegion::create($regionData);
                $stats['created']++;
                $this->logger->info("Created region: {$regionData['name']}");
            } else {
                // Update title and description if changed
                $changed = false;
                foreach (['title', 'description', 'max_blocks'] as $field) {
                    if ($region->{$field} !== $regionData[$field]) {
                        $region->{$field} = $regionData[$field];
                        $changed = true;
                    }
                }

                if ($changed) {
                    $region->save();
                    $stats['updated']++;
                    $this->logger->info("Updated region: {$regionData['name']}");
                }
            }
        }

        return $stats;
    }

    /**
     * Get default regions configuration
     */
    private function getDefaultRegions(): array
    {
        return [
            ['name' => 'header', 'title' => 'Header', 'description' => 'Top header region', 'max_blocks' => 10, 'is_active' => true],
            ['name' => 'header-nav', 'title' => 'Header Navigation', 'description' => 'Header navigation menu', 'max_blocks' => 5, 'is_active' => true],
            ['name' => 'header-user', 'title' => 'Header User Menu', 'description' => 'Header user menu (login/profile)', 'max_blocks' => 3, 'is_active' => true],

            ['name' => 'sidebar-left', 'title' => 'Left Sidebar', 'description' => 'Left sidebar region', 'max_blocks' => 20, 'is_active' => true],
            ['name' => 'sidebar', 'title' => 'Right Sidebar', 'description' => 'Right sidebar region', 'max_blocks' => 20, 'is_active' => true],

            ['name' => 'content', 'title' => 'Content', 'description' => 'Main content region', 'max_blocks' => 50, 'is_active' => true],

            ['name' => 'footer', 'title' => 'Footer', 'description' => 'Bottom footer region', 'max_blocks' => 10, 'is_active' => true],

            ['name' => 'homepage-hero', 'title' => 'Homepage Hero', 'description' => 'Homepage hero section', 'max_blocks' => 5, 'is_active' => true],
            ['name' => 'homepage-features', 'title' => 'Homepage Features', 'description' => 'Homepage features section', 'max_blocks' => 10, 'is_active' => true],
            ['name' => 'homepage-stats', 'title' => 'Homepage Stats', 'description' => 'Homepage statistics section', 'max_blocks' => 5, 'is_active' => true],
        ];
    }

    /**
     * Force sync blocks
     */
    public function forceSyncBlocks(): array
    {
        return $this->syncBlocks(force: true);
    }

    /**
     * Clear sync cache
     */
    public function clearSyncCache(): void
    {
        $this->cache->forget(self::CACHE_KEY);
        $this->logger->info('Block sync cache cleared');
    }

    /**
     * Get last sync time
     */
    public function getLastSyncTime(): ?int
    {
        return $this->cache->get(self::CACHE_KEY);
    }

    /**
     * Check if blocks are synced
     */
    public function isSynced(): bool
    {
        return $this->cache->has(self::CACHE_KEY);
    }
}
