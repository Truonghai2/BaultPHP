<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->create('page_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->string('visitor_ip', 50);
            $table->string('session_id', 100)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referer', 500)->nullable();
            $table->string('country_code', 5)->nullable();
            $table->string('device_type', 20)->nullable(); // desktop, mobile, tablet
            $table->timestamp('viewed_at');

            $table->index('page_id');
            $table->index('viewed_at');
            $table->index(['page_id', 'viewed_at']);
        });

        // Add foreign key for page_views
        try {
            $this->schema->getConnection()->exec('
                ALTER TABLE page_views 
                ADD CONSTRAINT page_views_page_id_foreign 
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
            ');
        } catch (\Exception $e) {
            // Foreign key might already exist
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('page_views');
    }
};
