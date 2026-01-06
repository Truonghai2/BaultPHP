<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if ($this->schema->hasTable('oauth_clients')) {
            $this->schema->table('oauth_clients', function (Blueprint $table) {
                if (!$this->schema->hasColumn('oauth_clients', 'user_id')) {
                    $table->string('user_id')->nullable();
                }
                if (!$this->schema->hasColumn('oauth_clients', 'provider')) {
                    $table->string('provider')->nullable();
                }
                if (!$this->schema->hasColumn('oauth_clients', 'is_personal_access_client')) {
                    $table->boolean('is_personal_access_client')->default(false);
                }
                if (!$this->schema->hasColumn('oauth_clients', 'is_password_client')) {
                    $table->boolean('is_password_client')->default(false);
                }
                if (!$this->schema->hasColumn('oauth_clients', 'is_revoked')) {
                    $table->boolean('is_revoked')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        if ($this->schema->hasTable('oauth_clients')) {
            $this->schema->table('oauth_clients', function (Blueprint $table) {
                if ($this->schema->hasColumn('oauth_clients', 'is_revoked')) {
                    $table->dropColumn('is_revoked');
                }
                if ($this->schema->hasColumn('oauth_clients', 'is_password_client')) {
                    $table->dropColumn('is_password_client');
                }
                if ($this->schema->hasColumn('oauth_clients', 'is_personal_access_client')) {
                    $table->dropColumn('is_personal_access_client');
                }
                if ($this->schema->hasColumn('oauth_clients', 'provider')) {
                    $table->dropColumn('provider');
                }
                if ($this->schema->hasColumn('oauth_clients', 'user_id')) {
                    $table->dropColumn('user_id');
                }
            });
        }
    }
};


