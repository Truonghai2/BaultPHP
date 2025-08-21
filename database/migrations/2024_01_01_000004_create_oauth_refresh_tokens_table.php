<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema->create('oauth_refresh_tokens', function (Blueprint $table) {
            $table->string('id', 100);
            $table->primary('id');
            $table->string('access_token_id', 100)->index();
            $table->boolean('revoked');
            $table->dateTime('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('oauth_refresh_tokens');
    }
};
