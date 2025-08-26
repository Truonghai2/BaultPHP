<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema->create('oauth_auth_codes', function (Blueprint $table) {
            $table->string('id', 100);
            $table->primary('id');
            $table->string('user_id')->index();
            $table->string('client_id');
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->dateTime('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('oauth_auth_codes');
    }
};
