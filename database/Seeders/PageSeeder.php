<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\Database\Seeder;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;
use Modules\Cms\Infrastructure\Models\BlockType;

/**
 * Page Seeder
 * 
 * Seeds sample pages for the CMS
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding pages...');

        $pages = [
            [
                'name' => 'Home',
                'slug' => 'home',
                'user_id' => 1,
            ],
            [
                'name' => 'About Us',
                'slug' => 'about-us',
                'user_id' => 1,
            ],
            [
                'name' => 'Services',
                'slug' => 'services',
                'user_id' => 1,
            ],
            [
                'name' => 'Contact',
                'slug' => 'contact',
                'user_id' => 1,
            ],
            [
                'name' => 'Blog',
                'slug' => 'blog',
                'user_id' => 1,
            ],
        ];

        foreach ($pages as $pageData) {
            $page = Page::where('slug', $pageData['slug'])->first();

            if (!$page) {
                $page = Page::create([
                    'name' => $pageData['name'],
                    'slug' => $pageData['slug'],
                    'user_id' => $pageData['user_id'],
                    'status' => 'published',
                    'published_at' => date('Y-m-d H:i:s'),
                    'language_code' => 'en',
                    'og_type' => 'website',
                    'robots' => 'index,follow',
                ]);
            }

            if ($page && $page->id) {
                $this->command->info("  ✓ Created/Found page: {$page->name} (ID: {$page->id})");
                $this->createPageBlocks($page);
            } else {
                $this->command->error("  ✗ Failed to create page: {$pageData['name']} (ID is null)");
            }
        }

        $this->command->info('✅ Pages seeded successfully!');
    }

    /**
     * Create sample page blocks for a page
     */
    private function createPageBlocks(Page $page): void
    {
        if (!$page->id) {
            $this->command->error("    ✗ Cannot create blocks: Page ID is null");
            return;
        }

        $blocks = [];

        switch ($page->slug) {
            case 'home':
                $blocks = [
                    [
                        'component_class' => 'Modules\\Cms\\Domain\\Blocks\\HomepageHeroBlock',
                        'order' => 0,
                    ],
                    [
                        'component_class' => 'Modules\\Cms\\Domain\\Blocks\\HomepageFeaturesBlock',
                        'order' => 1,
                    ],
                    [
                        'component_class' => 'Modules\\Cms\\Domain\\Blocks\\HomepageStatsBlock',
                        'order' => 2,
                    ],
                ];
                break;

            case 'about-us':
                $blocks = [
                    [
                        'component_class' => 'Modules\\Cms\\Domain\\Blocks\\TextBlock',
                        'order' => 0,
                    ],
                    [
                        'component_class' => 'Modules\\Cms\\Domain\\Blocks\\TeamBlock',
                        'order' => 1,
                    ],
                ];
                break;

            case 'services':
                $blocks = [
                    [
                        'component_class' => 'Modules\\Cms\\Domain\\Blocks\\HtmlBlock',
                        'order' => 0,
                    ],
                ];
                break;

            case 'contact':
                $blocks = [
                    [
                        'component_class' => 'Modules\\Cms\\Domain\\Blocks\\HtmlBlock',
                        'order' => 0,
                    ],
                ];
                break;

            case 'blog':
                $blocks = [
                    [
                        'component_class' => 'Modules\\Cms\\Domain\\Blocks\\HtmlBlock',
                        'order' => 0,
                    ],
                ];
                break;
        }

        foreach ($blocks as $blockData) {
            try {
                $blockType = $this->getBlockType($blockData['component_class']);
                
                if (!$blockType) {
                    $this->command->warn("    ⚠ Block type not found for {$blockData['component_class']}, skipping...");
                    continue;
                }
                
                $exists = PageBlock::where('page_id', $page->id)
                    ->where('block_type_id', $blockType->id)
                    ->where('region', 'content')
                    ->exists();
                
                if ($exists) {
                    $this->command->info("    - Block '{$blockType->name}' already exists");
                    continue;
                }
                
                if (!$page->id || !$blockType->id) {
                    $this->command->error("    ✗ Invalid IDs: page_id={$page->id}, block_type_id={$blockType->id}");
                    continue;
                }
                
                $block = PageBlock::create([
                    'page_id' => $page->id,
                    'block_type_id' => $blockType->id,
                    'region' => 'content', 
                    'sort_order' => $blockData['order'],
                    'visible' => true,
                ]);
                
                if ($block && $block->id) {
                    $this->command->info("    ✓ Created block: {$blockType->name} (ID: {$block->id})");
                } else {
                    $this->command->error("    ✗ Failed to create block: {$blockType->name} (block ID is null)");
                }
            } catch (\Exception $e) {
                $this->command->error("    ✗ Failed to create block '{$blockData['component_class']}': {$e->getMessage()}");
            }
        }
    }

    /**
     * Get block type from component class
     */
    private function getBlockType(string $componentClass): ?BlockType
    {
        return BlockType::where('class', $componentClass)->first();
    }
}
