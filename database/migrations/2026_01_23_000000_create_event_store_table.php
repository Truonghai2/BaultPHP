<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->schema->hasTable('event_store')) {
            return;
        }
        $this->schema->create('event_store', function (Blueprint $table) {
            $table->bigIncrements('id'); // Global position
            $table->uuid('event_id')->unique();
            $table->uuid('aggregate_id');
            $table->string('aggregate_type', 100);
            $table->integer('aggregate_version');
            $table->string('event_type', 100);
            $table->text('event_data'); // JSON
            $table->text('metadata')->nullable(); // JSON
            $table->integer('occurred_at');
            $table->integer('created_at');

            // Indexes for queries
            $table->index(['aggregate_id', 'aggregate_type', 'aggregate_version']);
            $table->index('event_type');
            $table->index('occurred_at');

            // Unique constraint: one version per aggregate
            $table->unique(['aggregate_id', 'aggregate_type', 'aggregate_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('event_store');
    }
};
