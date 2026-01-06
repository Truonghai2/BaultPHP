<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

/**
 * Restructure page_blocks to connect directly to block_types
 *
 * This migration simplifies the block system by:
 * - Removing the need for block_instances for page-specific blocks
 * - Connecting page_blocks directly to block_types
 * - Keeping all block configuration in page_blocks
 *
 * Benefits:
 * - Simpler architecture: Pages → PageBlocks → BlockTypes
 * - No duplicate data between page_blocks and block_instances
 * - Faster queries (one less join)
 * - Easier to understand and maintain
 */
return new class () extends Migration {
    public function up(): void
    {
        // First, backup existing page_blocks if it exists
        if ($this->schema->hasTable('page_blocks')) {
            echo "Backing up existing page_blocks...\n";
            $this->connection->statement('CREATE TABLE page_blocks_backup AS SELECT * FROM page_blocks');
        }

        // Drop old page_blocks table
        $this->schema->dropIfExists('page_blocks');

        // Create new page_blocks table with direct connection to block_types
        $this->schema->create('page_blocks', function (Blueprint $table) {
            $table->id();

            // Direct relationship to page and block_type
            $table->foreignId('page_id')
                ->constrained('pages')
                ->onDelete('cascade');

            $table->foreignId('block_type_id')
                ->constrained('block_types')
                ->onDelete('cascade');

            // Region for this block (simplified - just store the name)
            $table->string('region', 50)
                ->default('content');

            // Only store content if block generates dynamic content
            $table->text('content')
                ->nullable();

            // Display and ordering
            $table->integer('sort_order')
                ->default(0);

            $table->boolean('visible')
                ->default(true);

            // Visibility rules (optional)
            $table->json('visibility_rules')
                ->nullable();

            $table->json('allowed_roles')
                ->nullable();

            // Metadata
            $table->unsignedBigInteger('created_by')
                ->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['page_id', 'region', 'sort_order'], 'page_region_order_idx');
            $table->index(['page_id', 'visible'], 'page_visible_idx');
            $table->index('block_type_id');
            $table->index('created_by');
        });

        echo "✅ New page_blocks table created with direct block_type connection\n";
        echo "📊 Structure: Pages → PageBlocks → BlockTypes (simplified!)\n";
    }

    public function down(): void
    {
        // Restore from backup if exists
        if ($this->schema->hasTable('page_blocks_backup')) {
            echo "Restoring page_blocks from backup...\n";
            $this->schema->dropIfExists('page_blocks');
            $this->connection->statement('CREATE TABLE page_blocks AS SELECT * FROM page_blocks_backup');
            $this->schema->dropIfExists('page_blocks_backup');
            echo "✅ Restored\n";
        } else {
            echo "⚠️  No backup found. Creating empty page_blocks table...\n";
            $this->schema->dropIfExists('page_blocks');

            // Recreate old structure
            $this->schema->create('page_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('page_id')->constrained('pages')->onDelete('cascade');
                $table->string('component_class');
                $table->json('data')->nullable();
                $table->unsignedInteger('order')->default(0);
                $table->timestamps();
            });
        }
    }
};
