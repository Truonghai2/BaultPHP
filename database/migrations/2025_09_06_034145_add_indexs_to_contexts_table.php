<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
        $this->schema->table('contexts', function (Blueprint $table) {
            $table->dropUnique(['context_level', 'instance_id']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['path']);
        });
    }
};
