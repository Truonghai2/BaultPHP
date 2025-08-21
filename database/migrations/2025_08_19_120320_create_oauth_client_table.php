<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema->create('oauth_clients', function (Blueprint $table) {
            $table->string('id');
            $table->primary('id');
            $table->string('secret', 100)->nullable();
            $table->string('name');
            $table->text('redirect_uri');
            $table->boolean('is_confidential')->default(true);
            $table->text('scopes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('oauth_clients');
    }
};
