<?php

declare(strict_types=1);

namespace Modules\Cms\Console;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\Cms\Domain\Services\BlockSyncService;
use Modules\Cms\Infrastructure\Models\BlockRegion;
use Modules\Cms\Infrastructure\Models\BlockType;
use Modules\Cms\Infrastructure\Models\BlockInstance;

/**
 * Setup Default Blocks Command
 * 
 * Quickly setup default blocks for header, footer and other regions
 */
class SetupDefaultBlocksCommand extends BaseCommand
{
    public function __construct(
        Application $app,
        private readonly BlockSyncService $syncService
    ) {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'cms:setup-blocks {--force : Delete existing blocks and recreate}';
    }

    public function description(): string
    {
        return 'Setup default blocks for header, footer and other regions';
    }

    public function handle(): int
    {
        $this->io->title('CMS Block Setup');

        $force = $this->option('force');

        // Step 1: Sync block types and regions
        $this->io->section('Step 1: Syncing block types and regions...');
        
        try {
            $stats = $this->syncService->forceSyncBlocks();
            
            $this->io->table(
                ['Item', 'Count'],
                [
                    ['Block Types Created', $stats['types_created']],
                    ['Block Types Updated', $stats['types_updated']],
                    ['Regions Created', $stats['regions_created']],
                    ['Regions Updated', $stats['regions_updated']],
                ]
            );
            
            $this->io->success('Sync completed!');
        } catch (\Exception $e) {
            $this->io->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        // Step 2: Check existing instances
        $this->io->section('Step 2: Checking existing block instances...');
        
        $existingCount = BlockInstance::count();
        $this->io->info("Found {$existingCount} existing block instances");

        if ($existingCount > 0 && !$force) {
            $this->io->warning('Block instances already exist. Use --force to recreate them.');
            return self::SUCCESS;
        }

        if ($force && $existingCount > 0) {
            $this->io->warning("Deleting {$existingCount} existing block instances...");
            BlockInstance::truncate();
        }

        // Step 3: Create default block instances
        $this->io->section('Step 3: Creating default block instances...');
        
        $created = $this->createDefaultBlocks();
        
        $this->io->success("Created {$created} block instances!");
        
        // Step 4: Summary
        $this->io->section('Summary');
        $this->displayBlockSummary();
        
        return self::SUCCESS;
    }

    /**
     * Create default block instances
     */
    private function createDefaultBlocks(): int
    {
        $created = 0;
        $blocks = $this->getDefaultBlockConfiguration();

        foreach ($blocks as $blockConfig) {
            try {
                $region = BlockRegion::where('name', $blockConfig['region'])->first();
                if (!$region) {
                    $this->io->warning("Region '{$blockConfig['region']}' not found, skipping...");
                    continue;
                }

                $blockType = BlockType::where('name', $blockConfig['block_type'])->first();
                if (!$blockType) {
                    $this->io->warning("Block type '{$blockConfig['block_type']}' not found, skipping...");
                    continue;
                }

                BlockInstance::create([
                    'block_type_id' => $blockType->id,
                    'region_id' => $region->id,
                    'title' => $blockConfig['title'],
                    'config' => $blockConfig['config'] ?? [],
                    'visible' => $blockConfig['visible'] ?? true,
                    'weight' => $blockConfig['weight'] ?? 0,
                    'context_type' => $blockConfig['context_type'] ?? 'global',
                    'context_id' => $blockConfig['context_id'] ?? null,
                    'visibility_rules' => $blockConfig['visibility_rules'] ?? [],
                    'cache_enabled' => $blockConfig['cache_enabled'] ?? true,
                    'cache_ttl' => $blockConfig['cache_ttl'] ?? 3600,
                ]);

                $this->io->text("  <fg=green>✓</> Created: {$blockConfig['title']} in {$blockConfig['region']}");
                $created++;

            } catch (\Exception $e) {
                $this->io->error("Failed to create block '{$blockConfig['title']}': " . $e->getMessage());
            }
        }

        return $created;
    }

    /**
     * Get default block configuration
     */
    private function getDefaultBlockConfiguration(): array
    {
        return [
            // Header Navigation
            [
                'region' => 'header-nav',
                'block_type' => 'navigation',
                'title' => 'Main Navigation',
                'weight' => 0,
                'visible' => true,
                'config' => [
                    'menu_style' => 'horizontal',
                    'show_icons' => false,
                ],
            ],

            // Header User Menu
            [
                'region' => 'header-user',
                'block_type' => 'user_menu',
                'title' => 'User Menu',
                'weight' => 0,
                'visible' => true,
                'config' => [],
            ],

            // Search Block (Header)
            [
                'region' => 'header',
                'block_type' => 'search',
                'title' => 'Search',
                'weight' => 0,
                'visible' => true,
                'config' => [
                    'placeholder' => 'Search...',
                    'show_advanced' => false,
                ],
            ],

            // Footer Navigation
            [
                'region' => 'footer',
                'block_type' => 'navigation',
                'title' => 'Footer Navigation',
                'weight' => 0,
                'visible' => true,
                'config' => [
                    'menu_style' => 'footer',
                    'show_icons' => false,
                ],
            ],

            // Footer Menu Block
            [
                'region' => 'footer',
                'block_type' => 'menu',
                'title' => 'Footer Menu',
                'weight' => 1,
                'visible' => true,
                'config' => [
                    'links' => [
                        ['title' => 'About', 'url' => '/about'],
                        ['title' => 'Contact', 'url' => '/contact'],
                        ['title' => 'Privacy', 'url' => '/privacy'],
                        ['title' => 'Terms', 'url' => '/terms'],
                    ],
                ],
            ],

            // Homepage Hero
            [
                'region' => 'homepage-hero',
                'block_type' => 'homepage_hero',
                'title' => 'Homepage Hero',
                'weight' => 0,
                'visible' => true,
                'context_type' => 'page',
                'context_id' => 1, // Homepage
                'config' => [],
            ],

            // Homepage Features
            [
                'region' => 'homepage-features',
                'block_type' => 'homepage_features',
                'title' => 'Homepage Features',
                'weight' => 0,
                'visible' => true,
                'context_type' => 'page',
                'context_id' => 1, // Homepage
                'config' => [],
            ],

            // Homepage Stats
            [
                'region' => 'homepage-stats',
                'block_type' => 'homepage_stats',
                'title' => 'Homepage Stats',
                'weight' => 0,
                'visible' => true,
                'context_type' => 'page',
                'context_id' => 1, // Homepage
                'config' => [],
            ],
        ];
    }

    /**
     * Display block summary
     */
    private function displayBlockSummary(): void
    {
        $regions = ['header', 'header-nav', 'header-user', 'footer', 'sidebar', 'content'];
        $data = [];

        foreach ($regions as $regionName) {
            $region = BlockRegion::where('name', $regionName)->first();
            if (!$region) {
                continue;
            }

            $count = BlockInstance::where('region_id', $region->id)
                ->where('visible', true)
                ->count();

            $data[] = [$regionName, $count, $count > 0 ? '<fg=green>✓</>' : '<fg=red>✗</>'];
        }

        $this->io->table(
            ['Region', 'Blocks', 'Status'],
            $data
        );

        $this->io->newLine();
        $this->io->comment('You can now see blocks in header and footer!');
        $this->io->comment('Visit your website to verify: ' . config('app.url'));
    }
}

