<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create languages table
        $this->schema->create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // en, vi, fr
            $table->string('name', 100); // English, Vietnamese
            $table->string('native_name', 100); // English, Tiếng Việt
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('direction', 3)->default('ltr'); // ltr or rtl
            $table->timestamps();

            $table->index('code');
            $table->index('is_default');
            $table->index('is_active');
        });

        // Add language support to pages
        if (!$this->schema->hasColumn('pages', 'language_code')) {
            $this->schema->table('pages', function (Blueprint $table) {
                $table->string('language_code', 10)->default('en')->after('slug');
            });
        }

        if (!$this->schema->hasColumn('pages', 'translation_group_id')) {
            $this->schema->table('pages', function (Blueprint $table) {
                $table->unsignedBigInteger('translation_group_id')->nullable()->after('language_code');
            });
        }

        // Add indexes separately (after columns are committed)
        try {
            $this->schema->getConnection()->exec('CREATE INDEX idx_pages_language_code ON pages(language_code)');
        } catch (\Exception $e) {
            // Index might already exist
        }

        try {
            $this->schema->getConnection()->exec('CREATE INDEX idx_pages_translation_group_id ON pages(translation_group_id)');
        } catch (\Exception $e) {
            // Index might already exist
        }

        // Insert default languages
        $this->schema->getConnection()->exec("
            INSERT INTO languages (code, name, native_name, is_default, is_active, direction, created_at, updated_at) 
            VALUES 
                ('en', 'English', 'English', 1, 1, 'ltr', NOW(), NOW()),
                ('vi', 'Vietnamese', 'Tiếng Việt', 0, 1, 'ltr', NOW(), NOW())
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = [];
        if ($this->schema->hasColumn('pages', 'language_code')) {
            $columns[] = 'language_code';
        }
        if ($this->schema->hasColumn('pages', 'translation_group_id')) {
            $columns[] = 'translation_group_id';
        }

        if (!empty($columns)) {
            $this->schema->table('pages', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }

        $this->schema->dropIfExists('languages');
    }
};
