<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

/**
 * Create todo_user_stats table for projection.
 *
 * This is a read model - denormalized for fast queries!
 */
return new class () extends Migration {
    public function up(): void
    {
        $this->schema->create('todo_user_stats', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->unique();
            $table->integer('total_todos')->default(0);
            $table->integer('completed_todos')->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0);
            $table->timestamp('updated_at');

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('todo_user_stats');
    }
};
