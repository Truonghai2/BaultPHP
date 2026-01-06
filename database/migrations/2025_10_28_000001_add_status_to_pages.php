<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check and add columns only if they don't exist
        if (!$this->schema->hasColumn('pages', 'status')) {
            $this->schema->table('pages', function (Blueprint $table) {
                $table->enum('status', ['draft', 'published', 'archived'])
                    ->default('draft')
                    ->after('slug');
            });
        }

        if (!$this->schema->hasColumn('pages', 'published_at')) {
            $this->schema->table('pages', function (Blueprint $table) {
                $table->timestamp('published_at')->nullable()->after('status');
            });
        }

        if (!$this->schema->hasColumn('pages', 'scheduled_publish_at')) {
            $this->schema->table('pages', function (Blueprint $table) {
                $table->timestamp('scheduled_publish_at')->nullable()->after('published_at');
            });
        }

        // Set existing pages to published (only if status column exists and has null values)
        if ($this->schema->hasColumn('pages', 'status')) {
            $this->schema->getConnection()->exec("UPDATE pages SET status = 'published', published_at = NOW() WHERE status IS NULL OR status = ''");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = [];
        if ($this->schema->hasColumn('pages', 'status')) {
            $columns[] = 'status';
        }
        if ($this->schema->hasColumn('pages', 'published_at')) {
            $columns[] = 'published_at';
        }
        if ($this->schema->hasColumn('pages', 'scheduled_publish_at')) {
            $columns[] = 'scheduled_publish_at';
        }

        if (!empty($columns)) {
            $this->schema->table('pages', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
