<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     * 
     * Optimize sessions table để improve performance:
     * - Add composite index cho garbage collection
     * - Add payload_size tracking column
     * - Optimize column types
     * - Add created_at column
     */
    public function up(): void
    {
        $pdo = $this->schema->getConnection();
        
        // Check if columns exist before adding
        $hasCreatedAt = $this->schema->hasColumn('sessions', 'created_at');
        $hasPayloadSize = $this->schema->hasColumn('sessions', 'payload_size');
        
        $this->schema->table('sessions', function (Blueprint $table) use ($hasCreatedAt, $hasPayloadSize) {
            // Add created_at if not exists
            if (!$hasCreatedAt) {
                $table->integer('created_at')->nullable()->after('lifetime');
            }
            
            // Add payload_size if not exists
            if (!$hasPayloadSize) {
                $table->integer('payload_size')->unsigned()->default(0)->after('payload');
            }
        });
        
        // Check and create composite index if not exists
        $stmt = $pdo->query("SHOW INDEX FROM sessions WHERE Key_name = 'idx_activity_user'");
        $indexes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($indexes)) {
            // Drop old index if exists with different name
            $oldStmt = $pdo->query("SHOW INDEX FROM sessions WHERE Key_name LIKE '%last_activity%user_id%'");
            $oldIndexes = $oldStmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (!empty($oldIndexes)) {
                $oldIndexName = $oldIndexes[0]['Key_name'];
                $this->schema->statement("ALTER TABLE sessions DROP INDEX `{$oldIndexName}`");
            }
            
            $this->schema->statement(
                'ALTER TABLE sessions ADD INDEX idx_activity_user (last_activity, user_id)'
            );
        }
        
        // Check and create payload_size index if not exists
        $payloadStmt = $pdo->query("SHOW INDEX FROM sessions WHERE Key_name = 'idx_payload_size'");
        $payloadIndexes = $payloadStmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($payloadIndexes)) {
            $this->schema->statement(
                'ALTER TABLE sessions ADD INDEX idx_payload_size (payload_size)'
            );
        }
        
        // Update created_at for existing records (check again after potentially creating it)
        if (!$hasCreatedAt && $this->schema->hasColumn('sessions', 'created_at')) {
            $this->schema->statement(
                'UPDATE sessions SET created_at = last_activity WHERE created_at IS NULL'
            );
            
            // Make created_at NOT NULL
            $this->schema->statement(
                'ALTER TABLE sessions MODIFY created_at INT NOT NULL'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $pdo = $this->schema->getConnection();
        
        // Drop indexes if they exist
        $stmt = $pdo->query("SHOW INDEX FROM sessions WHERE Key_name IN ('idx_activity_user', 'idx_payload_size')");
        $indexes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($indexes as $index) {
            $this->schema->statement("ALTER TABLE sessions DROP INDEX `{$index['Key_name']}`");
        }
        
        // Drop columns if they exist
        if ($this->schema->hasColumn('sessions', 'created_at')) {
            $this->schema->statement('ALTER TABLE sessions DROP COLUMN created_at');
        }
        
        if ($this->schema->hasColumn('sessions', 'payload_size')) {
            $this->schema->statement('ALTER TABLE sessions DROP COLUMN payload_size');
        }
    }
};

