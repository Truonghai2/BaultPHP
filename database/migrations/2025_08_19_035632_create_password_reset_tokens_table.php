<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->schema->create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email');
            $table->primary('email');
            $table->string('token');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('password_reset_tokens');
    }
};
