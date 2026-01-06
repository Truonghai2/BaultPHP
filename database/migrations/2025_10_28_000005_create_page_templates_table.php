<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->schema->create('page_templates', function (Blueprint $table) {
            $table->id();

            // Template info
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('thumbnail', 500)->nullable();
            $table->string('category', 100)->default('general');

            // Template configuration
            $table->json('blocks_config'); // Pre-configured blocks
            $table->json('default_seo')->nullable(); // Default SEO settings

            // Settings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System templates can't be deleted
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index('category');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('page_templates');
    }
};
