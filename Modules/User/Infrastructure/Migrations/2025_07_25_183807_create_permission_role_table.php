<?php

namespace Modules\User\Infrastructure\Migrations;

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->schema->create('permission_role', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        $this->schema->statement('ALTER TABLE permission_role ADD PRIMARY KEY (permission_id, role_id)');

        $this->schema->statement('ALTER TABLE permission_role ADD CONSTRAINT fk_permission_role_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE');
        $this->schema->statement('ALTER TABLE permission_role ADD CONSTRAINT fk_permission_role_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('permission_role');
    }
};
