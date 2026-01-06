<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        echo "Adding ACL performance indexes...\n";

        $this->schema->table('role_assignments', function (Blueprint $table) {
            $table->index('user_id', 'idx_role_assignments_user_id');
            $table->index('role_id', 'idx_role_assignments_role_id');
            $table->index('context_id', 'idx_role_assignments_context_id');
            $table->index(['user_id', 'context_id'], 'idx_role_assignments_user_context');
        });
        echo "✓ Indexes created for role_assignments table.\n";

        $this->schema->table('permission_role', function (Blueprint $table) {
            $table->index('role_id', 'idx_permission_role_role_id');
            $table->index('permission_id', 'idx_permission_role_permission_id');
            $table->index(['role_id', 'permission_id'], 'idx_permission_role_combined');
        });
        echo "✓ Indexes created for permission_role table.\n";

        $this->schema->table('contexts', function (Blueprint $table) {
            $table->index(['context_level', 'instance_id'], 'idx_contexts_level_instance');
            $table->index('parent_id', 'idx_contexts_parent_id');
        });
        echo "✓ Indexes created for contexts table.\n";

        echo "ACL indexes optimization complete!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        echo "Dropping ACL performance indexes...\n";

        $this->schema->table('role_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_role_assignments_user_id');
            $table->dropIndex('idx_role_assignments_role_id');
            $table->dropIndex('idx_role_assignments_context_id');
            $table->dropIndex('idx_role_assignments_user_context');
        });

        $this->schema->table('permission_role', function (Blueprint $table) {
            $table->dropIndex('idx_permission_role_role_id');
            $table->dropIndex('idx_permission_role_permission_id');
            $table->dropIndex('idx_permission_role_combined');
        });

        $this->schema->table('contexts', function (Blueprint $table) {
            $table->dropIndex('idx_contexts_level_instance');
            $table->dropIndex('idx_contexts_parent_id');
        });

        echo "ACL indexes removed!\n";
    }
};
