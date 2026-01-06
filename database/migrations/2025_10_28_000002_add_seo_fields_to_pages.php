<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $columns = [
            'meta_title' => fn($t) => $t->string('meta_title', 255)->nullable()->after('name'),
            'meta_description' => fn($t) => $t->text('meta_description')->nullable()->after('meta_title'),
            'meta_keywords' => fn($t) => $t->string('meta_keywords', 500)->nullable()->after('meta_description'),
            'og_image' => fn($t) => $t->string('og_image', 500)->nullable()->after('meta_keywords'),
            'og_type' => fn($t) => $t->string('og_type', 50)->default('website')->after('og_image'),
            'canonical_url' => fn($t) => $t->string('canonical_url', 500)->nullable()->after('og_type'),
            'robots' => fn($t) => $t->string('robots', 100)->default('index,follow')->after('canonical_url'),
            'schema_data' => fn($t) => $t->json('schema_data')->nullable()->after('robots'),
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->schema->hasColumn('pages', $column)) {
                $this->schema->table('pages', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }
        
        // Set default values for existing rows (MySQL strict mode requirement)
        try {
            if ($this->schema->hasColumn('pages', 'og_type')) {
                $this->schema->getConnection()->exec("UPDATE pages SET og_type = 'website' WHERE og_type IS NULL OR og_type = ''");
            }
            if ($this->schema->hasColumn('pages', 'robots')) {
                $this->schema->getConnection()->exec("UPDATE pages SET robots = 'index,follow' WHERE robots IS NULL OR robots = ''");
            }
        } catch (\Exception $e) {
            // Ignore if update fails
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = ['meta_title', 'meta_description', 'meta_keywords', 'og_image', 
                    'og_type', 'canonical_url', 'robots', 'schema_data'];
        
        $existingColumns = array_filter($columns, fn($col) => $this->schema->hasColumn('pages', $col));
        
        if (!empty($existingColumns)) {
            $this->schema->table('pages', function (Blueprint $table) use ($existingColumns) {
                $table->dropColumn($existingColumns);
            });
        }
    }
};
