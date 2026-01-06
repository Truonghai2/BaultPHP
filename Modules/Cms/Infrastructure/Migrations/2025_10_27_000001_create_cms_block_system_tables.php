<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

/**
 * Migration for CMS Block System (Moodle-like)
 *
 * Tạo 3 bảng cho hệ thống block:
 * - block_types: Các loại block (TextBlock, HtmlBlock, etc.)
 * - block_regions: Các vùng (region) để đặt block (header, sidebar, etc.)
 * - block_instances: Các instance cụ thể của block
 */
return new class () extends Migration {
    public function up(): void
    {
        // Block Types Table
        $this->schema->create('block_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('Block type identifier');
            $table->string('title', 255)->comment('Human-readable title');
            $table->text('description')->nullable()->comment('Block description');
            $table->string('class', 255)->comment('PHP class name');
            $table->string('category', 50)->default('general')->comment('Block category');
            $table->string('icon', 50)->nullable()->comment('Icon for UI');
            $table->json('default_config')->nullable()->comment('Default configuration');
            $table->boolean('configurable')->default(true)->comment('Can be configured');
            $table->boolean('is_active')->default(true)->comment('Is active');
            $table->integer('version')->default(1)->comment('Block version');
            $table->timestamps();

            // Indexes
            $table->index('category');
            $table->index('is_active');
        });

        // Block Regions Table
        $this->schema->create('block_regions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('Region identifier');
            $table->string('title', 255)->comment('Human-readable title');
            $table->text('description')->nullable()->comment('Region description');
            $table->integer('max_blocks')->default(99)->comment('Maximum blocks allowed');
            $table->boolean('is_active')->default(true)->comment('Is active');
            $table->timestamps();

            // Indexes
            $table->index('is_active');
        });

        // Block Instances Table
        $this->schema->create('block_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_type_id')->constrained('block_types')->onDelete('cascade');
            $table->foreignId('region_id')->nullable()->constrained('block_regions')->onDelete('set null');

            // Context
            $table->string('context_type', 50)->default('global')->comment('Context: global, page, user');
            $table->unsignedBigInteger('context_id')->nullable()->comment('Context ID');

            // Content
            $table->string('title', 255)->nullable()->comment('Custom title');
            $table->json('config')->nullable()->comment('Instance configuration');
            $table->text('content')->nullable()->comment('Block content');

            // Display
            $table->integer('weight')->default(0)->comment('Sort order');
            $table->boolean('visible')->default(true)->comment('Is visible');

            // Visibility rules
            $table->string('visibility_mode', 50)->default('always')->comment('Visibility mode');
            $table->json('visibility_rules')->nullable()->comment('Visibility rules');
            $table->json('allowed_roles')->nullable()->comment('Allowed roles');
            $table->json('denied_roles')->nullable()->comment('Denied roles');

            // Metadata
            $table->unsignedBigInteger('created_by')->nullable()->comment('User ID who created this block');
            $table->timestamp('last_modified_at')->nullable();
            $table->timestamps();

            // Add index for created_by (no foreign key to avoid dependency on users table)
            $table->index('created_by');

            // Indexes
            $table->index(['region_id', 'weight']);
            $table->index('context_type');
            $table->index(['context_type', 'context_id']);
            $table->index('visible');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('block_instances');
        $this->schema->dropIfExists('block_regions');
        $this->schema->dropIfExists('block_types');
    }
};
