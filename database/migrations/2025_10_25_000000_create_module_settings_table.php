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
        $this->schema->create('module_settings', function (Blueprint $table) {
            $table->id();
            $table->string('module_name', 100)->index();
            $table->string('key', 191)->index();
            $table->text('value')->nullable();
            $table->string('type', 50)->default('string'); // string, integer, boolean, json, array
            $table->text('description')->nullable();
            $table->string('group', 100)->nullable()->index(); // Settings grouping
            $table->boolean('is_public')->default(false); // Can be accessed without auth
            $table->boolean('is_encrypted')->default(false); // Should be encrypted
            $table->integer('order')->default(0); // Display order
            $table->timestamps();

            // Unique constraint: one key per module
            $table->unique(['module_name', 'key'], 'module_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('module_settings');
    }
};

