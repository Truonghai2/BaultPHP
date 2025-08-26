<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema->create('oauth_access_tokens', function (Blueprint $table) {
            $table->string('id', 100);
            $table->primary('id');
            $table->string('user_id')->nullable()->index();
            $table->string('client_id');
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('oauth_access_tokens');
    }
};
