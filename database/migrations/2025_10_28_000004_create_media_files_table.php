<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->schema->create('media_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');

            // File information
            $table->string('filename', 255);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size'); // bytes
            $table->string('path', 500);
            $table->string('url', 500);

            // Image metadata
            $table->string('alt_text', 500)->nullable();
            $table->string('title', 255)->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();

            // Organization
            $table->string('folder', 255)->default('/');
            $table->json('tags')->nullable();

            // SEO
            $table->string('caption', 500)->nullable();
            $table->text('description')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('folder');
            $table->index('mime_type');
            $table->index('created_at');
        });

        // Add foreign key separately
        try {
            $this->schema->getConnection()->exec('
                ALTER TABLE media_files 
                ADD CONSTRAINT media_files_user_id_foreign 
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ');
        } catch (\Exception $e) {
            // Foreign key might already exist
        }

        // Add fulltext index
        try {
            $this->schema->getConnection()->exec(
                'ALTER TABLE media_files ADD FULLTEXT INDEX idx_media_search (filename, original_filename, alt_text, title)',
            );
        } catch (\Exception $e) {
            // Index might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('media_files');
    }
};
