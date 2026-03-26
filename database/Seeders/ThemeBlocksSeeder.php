<?php

namespace Database\Seeders;

use Core\Database\Seeder;
use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\Cms\Infrastructure\Models\BlockRegion;
use Modules\Cms\Infrastructure\Models\BlockType;

class ThemeBlocksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Setup dynamic header (navigation, user menu) and footer blocks
     * to match the default theme.
     */
    public function run(): void
    {
        // 1. Ensure Regions Exist
        $regions = ['header-nav', 'header-user', 'footer'];
        foreach ($regions as $name) {
            if (!BlockRegion::where('name', $name)->exists()) {
                BlockRegion::create([
                    'name' => $name, 
                    'description' => ucfirst(str_replace('-', ' ', $name)) . ' Region'
                ]);
            }
        }

        // 2. Header Navigation
        $this->createHeaderNavigation();

        // 3. Header User Menu
        $this->createHeaderUserMenu();

        // 4. Footer
        $this->createFooter();
    }

    private function createHeaderNavigation(): void
    {
        $blockType = BlockType::where('name', 'navigation')->first();
        if (!$blockType) return;

        $region = BlockRegion::where('name', 'header-nav')->first();
        
        $config = [
            'menu_items' => [
                ['label' => 'Home', 'url' => '/', 'target' => '_self'],
                ['label' => 'About', 'url' => '/about-us', 'target' => '_self'],
                ['label' => 'Contact', 'url' => '/contact', 'target' => '_self'],
            ],
            'style' => 'horizontal',
        ];

        BlockInstance::updateOrCreate(
            ['region_id' => $region->id, 'block_type_id' => $blockType->id],
            [
                'name' => 'Main Navigation',
                'config' => $config,
                'sort_order' => 0,
                'weight' => 0,
                'visible' => true,
                'context_type' => 'global',
                'visibility_mode' => 'always',
                'visibility_rules' => [],
                'allowed_roles' => [],
                'denied_roles' => [],
            ]
        );
    }

    private function createHeaderUserMenu(): void
    {
        $blockType = BlockType::where('name', 'user-menu')->first();
        if (!$blockType) return;

        $region = BlockRegion::where('name', 'header-user')->first();

        BlockInstance::updateOrCreate(
            ['region_id' => $region->id, 'block_type_id' => $blockType->id],
            [
                'name' => 'User Menu',
                'config' => [], // User menu has minimal config
                'sort_order' => 0,
                'weight' => 0,
                'visible' => true,
                'context_type' => 'global',
                'visibility_mode' => 'always',
                'visibility_rules' => [],
                'allowed_roles' => [],
                'denied_roles' => [],
            ]
        );
    }

    private function createFooter(): void
    {
        $blockType = BlockType::where('name', 'footer')->first();
        if (!$blockType) return;

        $region = BlockRegion::where('name', 'footer')->first();

        $config = [
            'columns' => [
                [
                    'title' => 'Product',
                    'links' => [
                        ['label' => 'Features', 'url' => '/features'],
                        ['label' => 'Pricing', 'url' => '/pricing'],
                        ['label' => 'Documentation', 'url' => '/docs'],
                    ],
                ],
                [
                    'title' => 'Company',
                    'links' => [
                        ['label' => 'About', 'url' => '/about'],
                        ['label' => 'Blog', 'url' => '/blog'],
                        ['label' => 'Contact', 'url' => '/contact'],
                    ],
                ],
                [
                    'title' => 'Resources',
                    'links' => [
                        ['label' => 'Guides', 'url' => '/guides'],
                        ['label' => 'API Reference', 'url' => '/api'],
                        ['label' => 'Support', 'url' => '/support'],
                    ],
                ],
                [
                    'title' => 'Legal',
                    'links' => [
                        ['label' => 'Privacy', 'url' => '/privacy'],
                        ['label' => 'Terms', 'url' => '/terms'],
                        ['label' => 'License', 'url' => '/license'],
                    ],
                ],
            ],
            'copyright' => '© ' . date('Y') . ' BaultPHP Framework. All rights reserved.',
            'social_links' => [
                ['platform' => 'GitHub', 'url' => 'https://github.com/Truonghai2/BaultPHP', 'icon' => '📦'],
                ['platform' => 'Twitter', 'url' => 'https://twitter.com/baultphp', 'icon' => '🐦'],
            ],
        ];

        BlockInstance::updateOrCreate(
            ['region_id' => $region->id, 'block_type_id' => $blockType->id],
            [
                'name' => 'Main Footer',
                'config' => $config,
                'sort_order' => 0,
                'weight' => 0,
                'visible' => true,
                'context_type' => 'global',
                'visibility_mode' => 'always', // Added default
                'visibility_rules' => [],      // Added default
                'allowed_roles' => [],         // Added default
                'denied_roles' => [],          // Added default
            ]
        );
    }
}
