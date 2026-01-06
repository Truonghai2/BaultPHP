<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if ($this->schema->hasColumn('oauth_clients', 'redirect_uri')) {
            $this->schema->table('oauth_clients', function (Blueprint $table) {
                $table->renameColumn('redirect_uri', 'redirect');
            });
        }

        try {
            $this->schema->statement('CREATE INDEX oauth_access_tokens_client_id_idx ON oauth_access_tokens(client_id)');
        } catch (\Exception $e) {
        }

        try {
            $this->schema->statement('CREATE INDEX oauth_access_tokens_user_revoked_idx ON oauth_access_tokens(user_id, revoked)');
        } catch (\Exception $e) {
        }

        try {
            $this->schema->statement('CREATE INDEX oauth_access_tokens_expires_at_idx ON oauth_access_tokens(expires_at)');
        } catch (\Exception $e) {
        }

        try {
            $this->schema->statement('CREATE INDEX oauth_refresh_tokens_access_token_idx ON oauth_refresh_tokens(access_token_id)');
        } catch (\Exception $e) {
        }

        try {
            $this->schema->statement('CREATE INDEX oauth_refresh_tokens_expires_at_idx ON oauth_refresh_tokens(expires_at)');
        } catch (\Exception $e) {
        }

        try {
            $this->schema->statement('CREATE INDEX oauth_auth_codes_client_id_idx ON oauth_auth_codes(client_id)');
        } catch (\Exception $e) {
        }

        try {
            $this->schema->statement('CREATE INDEX oauth_auth_codes_expires_at_idx ON oauth_auth_codes(expires_at)');
        } catch (\Exception $e) {
        }

        echo "OAuth schema improvements applied successfully.\n";
        echo "Note: Indexes added for performance.\n";
    }

    public function down(): void
    {
        try {
            $this->schema->statement('DROP INDEX oauth_auth_codes_client_id_idx ON oauth_auth_codes');
        } catch (\Exception $e) {
        }

        try {
            $this->schema->statement('DROP INDEX oauth_auth_codes_expires_at_idx ON oauth_auth_codes');
        } catch (\Exception $e) {
        }

        try {
            $this->schema->statement('DROP INDEX oauth_refresh_tokens_access_token_idx ON oauth_refresh_tokens');
        } catch (\Exception $e) {
        }

        try {
            $this->schema->statement('DROP INDEX oauth_refresh_tokens_expires_at_idx ON oauth_refresh_tokens');
        } catch (\Exception $e) {
        }

        try {
            $this->schema->statement('DROP INDEX oauth_access_tokens_client_id_idx ON oauth_access_tokens');
        } catch (\Exception $e) {
        }

        try {
            $this->schema->statement('DROP INDEX oauth_access_tokens_user_revoked_idx ON oauth_access_tokens');
        } catch (\Exception $e) {
        }

        try {
            $this->schema->statement('DROP INDEX oauth_access_tokens_expires_at_idx ON oauth_access_tokens');
        } catch (\Exception $e) {
            // Ignore
        }

        // Rename column back
        $this->schema->table('oauth_clients', function (Blueprint $table) {
            if ($this->schema->hasColumn('oauth_clients', 'redirect') && !$this->schema->hasColumn('oauth_clients', 'redirect_uri')) {
                $table->renameColumn('redirect', 'redirect_uri');
            } elseif ($this->schema->hasColumn('oauth_clients', 'redirect')) {
                $table->dropColumn('redirect');
            }
        });
    }
};
