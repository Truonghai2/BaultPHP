<?php

use Core\Schema\Migration;
use Modules\Cms\Infrastructure\Models\{Page, BlockInstance, PageBlock};

/**
 * Migrate data from block_instances (page context) to page_blocks
 * 
 * This migration moves all page-specific blocks from block_instances
 * back to the simplified page_blocks table.
 * 
 * After this migration:
 * - page_blocks: stores all page-specific blocks (direct connection to block_types)
 * - block_instances: only stores global blocks and other context blocks
 */
return new class extends Migration
{
    public function up(): void
    {
        echo "🔄 Migrating page-specific block_instances to page_blocks...\n\n";

        // Check if tables exist
        if (!$this->schema->hasTable('block_instances')) {
            echo "⚠️  block_instances table not found. Skipping migration.\n";
            return;
        }

        if (!$this->schema->hasTable('page_blocks')) {
            echo "⚠️  page_blocks table not found. Run structure migration first.\n";
            return;
        }

        // Get all page-context block instances
        $pageBlockInstances = BlockInstance::where('context_type', 'page')
            ->whereNotNull('context_id')
            ->get();

        if ($pageBlockInstances->isEmpty()) {
            echo "✅ No page block instances to migrate.\n";
            return;
        }

        $migrated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($pageBlockInstances as $instance) {
            try {
                // Verify page exists
                $page = Page::find($instance->context_id);
                if (!$page) {
                    $skipped++;
                    $errors[] = "Page {$instance->context_id} not found for instance {$instance->id}";
                    continue;
                }

                // Extract region name from region
                $region = $this->extractRegionName($instance, $page);

                // Check if already exists
                $exists = PageBlock::where('page_id', $page->id)
                    ->where('block_type_id', $instance->block_type_id)
                    ->where('region', $region)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Create PageBlock from BlockInstance (no title/config - taken from block_type)
                $pageBlock = new PageBlock([
                    'page_id' => $page->id,
                    'block_type_id' => $instance->block_type_id,
                    'region' => $region,
                    'content' => $instance->content,
                    'sort_order' => $instance->weight,
                    'visible' => $instance->visible,
                    'visibility_rules' => $instance->visibility_rules,
                    'allowed_roles' => $instance->allowed_roles,
                    'created_by' => $instance->created_by,
                ]);

                $pageBlock->created_at = $instance->created_at;
                $pageBlock->updated_at = $instance->updated_at;
                $pageBlock->save();

                $migrated++;

                if ($migrated % 50 === 0) {
                    echo "  Progress: {$migrated} blocks migrated...\n";
                }

            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Instance {$instance->id}: {$e->getMessage()}";
            }
        }

        echo "\n📊 Migration Summary:\n";
        echo "  ✅ Migrated: {$migrated} block_instances → page_blocks\n";
        echo "  ⏭️  Skipped: {$skipped}\n";
        echo "  📦 Total processed: " . ($migrated + $skipped) . "\n";

        if (!empty($errors)) {
            echo "\n⚠️  Errors encountered:\n";
            foreach (array_slice($errors, 0, 10) as $error) {
                echo "  - {$error}\n";
            }
            if (count($errors) > 10) {
                echo "  ... and " . (count($errors) - 10) . " more errors\n";
            }
        }

        echo "\n✨ Migration completed!\n";
        echo "ℹ️  Page-context block_instances are still preserved.\n";
        echo "   You can delete them manually after verification:\n";
        echo "   DELETE FROM block_instances WHERE context_type = 'page';\n\n";
    }

    public function down(): void
    {
        echo "⚠️  Rollback: Migrating page_blocks back to block_instances\n\n";

        $pageBlocks = PageBlock::all();

        if ($pageBlocks->isEmpty()) {
            echo "✅ No page blocks to rollback.\n";
            return;
        }

        $migrated = 0;
        $skipped = 0;

        foreach ($pageBlocks as $pageBlock) {
            try {
                // Find or create region
                $regionName = "page-{$pageBlock->page->slug}-{$pageBlock->region}";
                $region = \Modules\Cms\Infrastructure\Models\BlockRegion::firstOrCreate(
                    ['name' => $regionName],
                    [
                        'title' => ucfirst($pageBlock->region),
                        'description' => "Region for page {$pageBlock->page->name}",
                        'max_blocks' => 50,
                        'is_active' => true,
                    ]
                );

                // Check if already exists
                $exists = BlockInstance::where('context_type', 'page')
                    ->where('context_id', $pageBlock->page_id)
                    ->where('block_type_id', $pageBlock->block_type_id)
                    ->where('region_id', $region->id)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Create BlockInstance (get title/config from blockType)
                $instance = new BlockInstance([
                    'block_type_id' => $pageBlock->block_type_id,
                    'region_id' => $region->id,
                    'context_type' => 'page',
                    'context_id' => $pageBlock->page_id,
                    'title' => $pageBlock->blockType->title,
                    'config' => $pageBlock->blockType->default_config,
                    'content' => $pageBlock->content,
                    'weight' => $pageBlock->sort_order,
                    'visible' => $pageBlock->visible,
                    'visibility_mode' => 'always',
                    'visibility_rules' => $pageBlock->visibility_rules,
                    'allowed_roles' => $pageBlock->allowed_roles,
                    'created_by' => $pageBlock->created_by,
                ]);

                $instance->created_at = $pageBlock->created_at;
                $instance->updated_at = $pageBlock->updated_at;
                $instance->save();

                $migrated++;

            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        echo "\n📊 Rollback Summary:\n";
        echo "  ✅ Restored: {$migrated} page_blocks → block_instances\n";
        echo "  ⏭️  Skipped: {$skipped}\n";
        echo "\n✅ Rollback completed!\n";
    }

    /**
     * Extract region name from block instance
     */
    private function extractRegionName(BlockInstance $instance, Page $page): string
    {
        // If region exists, extract the simple name
        if ($instance->region) {
            $regionName = $instance->region->name;
            
            // Remove page-specific prefix if exists
            // e.g., "page-home-content" -> "content"
            $prefix = "page-{$page->slug}-";
            if (str_starts_with($regionName, $prefix)) {
                return substr($regionName, strlen($prefix));
            }
            
            return $regionName;
        }

        // Default to 'content' if no region
        return 'content';
    }
};

