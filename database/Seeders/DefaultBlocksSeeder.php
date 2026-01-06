<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\Database\Seeder;
use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\Cms\Infrastructure\Models\BlockRegion;
use Modules\Cms\Infrastructure\Models\BlockType;

/**
 * Default Blocks Seeder
 *
 * Creates BlockInstances for global regions (header, footer, sidebar)
 * This is separate from PageBlocks which are page-specific
 */
class DefaultBlocksSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating default block instances...');

        // Skip if already exists
        if (BlockInstance::count() > 0) {
            $this->command->warn('  ⚠ Block instances already exist. Skipping...');
            return;
        }

        $this->createHeaderBlocks();
        $this->createFooterBlocks();

        $this->command->info('✅ Default block instances created!');
    }

    /**
     * Create header block instances
     */
    private function createHeaderBlocks(): void
    {
        $this->command->info('  Creating header blocks...');

        $blocks = [
            [
                'region' => 'header-nav',
                'block_type' => 'navigation',
                'title' => 'Main Navigation',
                'weight' => 0,
                'config' => [
                    'menu_style' => 'horizontal',
                    'show_icons' => false,
                ],
            ],
            [
                'region' => 'header-user',
                'block_type' => 'user_menu',
                'title' => 'User Menu',
                'weight' => 0,
                'config' => [],
            ],
            [
                'region' => 'header',
                'block_type' => 'search',
                'title' => 'Search',
                'weight' => 0,
                'config' => [
                    'placeholder' => 'Search...',
                    'show_advanced' => false,
                ],
            ],
        ];

        $created = 0;
        foreach ($blocks as $blockData) {
            if ($this->createBlockInstance($blockData)) {
                $created++;
            }
        }

        $this->command->info("    ✓ Created {$created} header blocks");
    }

    /**
     * Create footer block instances
     */
    private function createFooterBlocks(): void
    {
        $this->command->info('  Creating footer blocks...');

        $blocks = [
            [
                'region' => 'footer',
                'block_type' => 'navigation',
                'title' => 'Footer Navigation',
                'weight' => 0,
                'config' => [
                    'menu_style' => 'footer',
                    'show_icons' => false,
                ],
            ],
            [
                'region' => 'footer',
                'block_type' => 'menu',
                'title' => 'Footer Menu',
                'weight' => 1,
                'config' => [
                    'title' => 'Quick Links',
                    'links' => [
                        ['title' => 'About Us', 'url' => '/about-us'],
                        ['title' => 'Contact', 'url' => '/contact'],
                        ['title' => 'Privacy Policy', 'url' => '/privacy'],
                        ['title' => 'Terms of Service', 'url' => '/terms'],
                    ],
                ],
            ],
        ];

        $created = 0;
        foreach ($blocks as $blockData) {
            if ($this->createBlockInstance($blockData)) {
                $created++;
            }
        }

        $this->command->info("    ✓ Created {$created} footer blocks");
    }

    /**
     * Create a block instance
     */
    private function createBlockInstance(array $data): bool
    {
        $region = BlockRegion::where('name', $data['region'])->first();
        if (!$region) {
            $this->command->warn("    ⚠ Region '{$data['region']}' not found");
            return false;
        }

        $blockType = BlockType::where('name', $data['block_type'])->first();
        if (!$blockType) {
            $this->command->warn("    ⚠ Block type '{$data['block_type']}' not found");
            return false;
        }

        BlockInstance::create([
            'block_type_id' => $blockType->id,
            'region_id' => $region->id,
            'title' => $data['title'],
            'config' => $data['config'] ?? [],
            'visible' => $data['visible'] ?? true,
            'weight' => $data['weight'] ?? 0,
            'context_type' => $data['context_type'] ?? 'global',
            'context_id' => $data['context_id'] ?? null,
            'visibility_mode' => $data['visibility_mode'] ?? 'always',
            'visibility_rules' => $data['visibility_rules'] ?? [],
            'cache_enabled' => $data['cache_enabled'] ?? true,
            'cache_ttl' => $data['cache_ttl'] ?? 3600,
        ]);

        return true;
    }
}
