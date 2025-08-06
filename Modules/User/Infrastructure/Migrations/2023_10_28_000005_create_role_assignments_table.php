<?php

namespace Modules\User\Infrastructure\Migrations;

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema->create('role_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('context_id');

            $table->unique(['user_id', 'context_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('role_assignments');
    }
};
