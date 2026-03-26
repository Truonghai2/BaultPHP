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
        $this->schema->create('todos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->uuid('user_id');
            $table->boolean('completed')->default(false);
            $table->integer('created_at');
            $table->integer('completed_at')->nullable();

            // Indexes for queries
            $table->index('user_id');
            $table->index(['user_id', 'completed']);
            $table->index('created_at');

            // Foreign key
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('todos');
    }
};
