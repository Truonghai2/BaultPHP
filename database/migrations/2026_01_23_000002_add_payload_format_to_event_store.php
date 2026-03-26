<?php

/**
 * Add payload_format column to event_store table.
 * 
 * Supports both JSON and Protobuf serialization formats.
 */

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class extends Migration {
    public function up(): void
    {
        if (!$this->schema->hasTable('event_store')) {
            return;
        }
        if ($this->schema->hasColumn('event_store', 'payload_format')) {
            return;
        }
        // Add column first (grammar processes commands before columns, so index must be separate)
        $this->schema->table('event_store', function (Blueprint $table) {
            $table->string('payload_format', 20)->default('json');
        });
        // Add index in a second ALTER so the column exists
        $this->schema->table('event_store', function (Blueprint $table) {
            $table->index('payload_format');
        });
    }

    public function down(): void
    {
        if (!$this->schema->hasTable('event_store')) {
            return;
        }
        if (!$this->schema->hasColumn('event_store', 'payload_format')) {
            return;
        }
        $this->schema->table('event_store', function (Blueprint $table) {
            $table->dropColumn('payload_format');
        });
    }
};
