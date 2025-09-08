<?php

namespace Modules\User\Infrastructure\Migrations;

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema->create('contexts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('context_level', 100);
            $table->unsignedInteger('instance_id');
            $table->string('path');
            $table->tinyInteger('depth');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('contexts');
    }
};
