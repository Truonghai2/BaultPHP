<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

class create_modules_table extends Migration
{
    public function up(): void
    {
        $this->schema->create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('version')->default('1.0.0');
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('modules');
    }
};