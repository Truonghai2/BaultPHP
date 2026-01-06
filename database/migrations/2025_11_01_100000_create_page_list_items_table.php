<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

/**
 * Creates the page_list_items table.
 * This table serves as a denormalized read model for fast page list queries.
 * It is populated and maintained by the PageListProjection.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->schema->create('page_list_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('page_uuid')->unique()->comment('Links to the PageAggregate ID');
            $table->string('name');
            $table->string('slug')->index();
            $table->unsignedBigInteger('author_id')->nullable()->index();
            $table->string('status', 50)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes(); // For restore() functionality in the projection
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('page_list_items');
    }
};
