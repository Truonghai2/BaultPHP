<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure idempotency: drop existing indexes if they already exist (MySQL 8 supports IF EXISTS)
        try {
            $this->schema->statement('ALTER TABLE contexts DROP INDEX contexts_context_level_instance_id_unique');
        } catch (\Throwable $e) {
        }
        try {
            $this->schema->statement('ALTER TABLE contexts DROP INDEX contexts_parent_id_index');
        } catch (\Throwable $e) {
        }
        try {
            $this->schema->statement('ALTER TABLE contexts DROP INDEX contexts_path_index');
        } catch (\Throwable $e) {
        }

        $this->schema->table('contexts', function (Blueprint $table) {
            $table->unique(['context_level', 'instance_id']);
            $table->index(['parent_id']);
            $table->index(['path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Be defensive: drop indexes if they exist
        try {
            $this->schema->statement('ALTER TABLE contexts DROP INDEX contexts_context_level_instance_id_unique');
        } catch (\Throwable $e) {
        }
        try {
            $this->schema->statement('ALTER TABLE contexts DROP INDEX contexts_parent_id_index');
        } catch (\Throwable $e) {
        }
        try {
            $this->schema->statement('ALTER TABLE contexts DROP INDEX contexts_path_index');
        } catch (\Throwable $e) {
        }
    }
};
