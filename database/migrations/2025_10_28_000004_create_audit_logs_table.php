<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema->create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Event information
            $table->string('event_type', 100)->index(); // oauth.token.issued, user.login, model.created, etc.
            $table->string('event_category', 50)->index(); // oauth, auth, crud, system

            // Actor (who performed the action)
            $table->string('user_id')->nullable()->index();
            $table->string('user_type')->nullable(); // User, Admin, System
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // Target (what was affected)
            $table->string('auditable_type')->nullable(); // Model class name
            $table->string('auditable_id')->nullable(); // Model ID
            $table->index(['auditable_type', 'auditable_id']);

            // Context and metadata
            $table->text('description')->nullable();
            $table->json('old_values')->nullable(); // For update/delete operations
            $table->json('new_values')->nullable(); // For create/update operations
            $table->json('metadata')->nullable(); // Additional context

            // Classification
            $table->enum('severity', ['debug', 'info', 'warning', 'error', 'critical'])->default('info');
            $table->boolean('is_sensitive')->default(false); // Mark sensitive operations

            $table->timestamps();

            // Indexes for performance
            $table->index('created_at');
            $table->index(['event_category', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        echo "Audit logs table created successfully.\n";
    }

    public function down(): void
    {
        $this->schema->dropIfExists('audit_logs');
    }
};
