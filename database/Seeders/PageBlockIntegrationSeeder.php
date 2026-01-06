<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\Database\Seeder;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;
use Modules\Cms\Infrastructure\Models\BlockType;

/**
 * Page-Block Integration Seeder
 * 
 * Assigns blocks to pages using simplified architecture
 * Uses ONLY real block types - NO FAKE DATA
 * 
 * Pages → PageBlocks → BlockTypes (which have default configs)
 */
class PageBlockIntegrationSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Integrating pages with block system...');

        $this->assignBlocksToPages();

        $this->command->info('✅ Page-Block integration completed!');
    }

    /**
     * Assign blocks to all pages
     */
    private function assignBlocksToPages(): void
    {
        $pages = Page::all();

        if ($pages->isEmpty()) {
            $this->command->warn('  ⚠ No pages found. Run PageSeeder first.');
            return;
        }

        foreach ($pages as $page) {
            $this->assignBlocksForPage($page);
        }
    }

    /**
     * Assign blocks for a specific page
     */
    private function assignBlocksForPage(Page $page): void
    {
        switch ($page->slug) {
            case 'home':
                $this->createHomePageBlocks($page);
                break;
                
            case 'about-us':
                $this->createAboutPageBlocks($page);
                break;
                
            default:
                // Other pages use default layout
                $this->command->info("  - Page '{$page->name}' uses default layout (no page-specific blocks)");
                break;
        }
    }

    /**
     * Create blocks for home page
     */
    private function createHomePageBlocks(Page $page): void
    {
        // Homepage uses 3 homepage blocks
        $blocks = [
            ['name' => 'homepage-hero', 'region' => 'content', 'order' => 0],
            ['name' => 'homepage-features', 'region' => 'content', 'order' => 1],
            ['name' => 'homepage-stats', 'region' => 'content', 'order' => 2],
        ];

        foreach ($blocks as $blockData) {
            $blockType = BlockType::where('name', $blockData['name'])->first();
            
            if (!$blockType) {
                $this->command->warn("  ⚠ Block type '{$blockData['name']}' not found, skipping...");
                continue;
            }

            $this->createPageBlock($page, $blockType, $blockData['region'], $blockData['order']);
        }

        $this->command->info("  ✓ Assigned blocks to page: {$page->name}");
    }

    /**
     * Create blocks for about page
     */
    private function createAboutPageBlocks(Page $page): void
    {
        // About page uses text and team blocks
        $blocks = [
            ['name' => 'text-block', 'region' => 'content', 'order' => 0],
            ['name' => 'team', 'region' => 'content', 'order' => 1],
        ];

        foreach ($blocks as $blockData) {
            $blockType = BlockType::where('name', $blockData['name'])->first();
            
            if (!$blockType) {
                $this->command->warn("  ⚠ Block type '{$blockData['name']}' not found, skipping...");
                continue;
            }

            $this->createPageBlock($page, $blockType, $blockData['region'], $blockData['order']);
        }

        $this->command->info("  ✓ Assigned blocks to page: {$page->name}");
    }

    /**
     * Helper to create a page block
     */
    private function createPageBlock(
        Page $page,
        BlockType $blockType,
        string $region,
        int $order
    ): void {
        // Check if block already exists
        $existing = PageBlock::where('page_id', $page->id)
            ->where('block_type_id', $blockType->id)
            ->where('region', $region)
            ->first();

        if ($existing) {
            return; // Already exists
        }

        // Create page block (uses default config from block type)
        PageBlock::create([
            'page_id' => $page->id,
            'block_type_id' => $blockType->id,
            'region' => $region,
            'sort_order' => $order,
            'visible' => true,
            'created_by' => 1, // Admin user
        ]);
    }
}
