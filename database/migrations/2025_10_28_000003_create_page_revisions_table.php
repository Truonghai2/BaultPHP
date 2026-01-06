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
        $this->schema->create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('user_id');
            
            // Revision data
            $table->json('content');  // Full snapshot of page + blocks
            $table->integer('revision_number');
            $table->string('change_summary', 500)->nullable();
            
            // Metadata
            $table->string('ip_address', 50)->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('page_id');
            $table->index('user_id');
            $table->index('created_at');
            $table->index(['page_id', 'revision_number']);
        });
        
        // Add foreign keys separately (workaround for Blueprint foreign() issue)
        try {
            $this->schema->getConnection()->exec('
                ALTER TABLE page_revisions 
                ADD CONSTRAINT page_revisions_page_id_foreign 
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
            ');
        } catch (\Exception $e) {
            // Foreign key might already exist
        }
        
        try {
            $this->schema->getConnection()->exec('
                ALTER TABLE page_revisions 
                ADD CONSTRAINT page_revisions_user_id_foreign 
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ');
        } catch (\Exception $e) {
            // Foreign key might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('page_revisions');
    }
};
