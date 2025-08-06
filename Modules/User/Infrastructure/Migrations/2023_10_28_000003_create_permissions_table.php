<?php

namespace Modules\User\Infrastructure\Migrations;

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema->create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('captype', 20)->default('notAll')->comment("Permission type: 'notall', 'all', 'read' or 'write'.");
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('permissions');
    }
};
